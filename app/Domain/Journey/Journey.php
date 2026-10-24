<?php

namespace App\Domain\Journey;

use App\Domain\Editorial\AuditLog;
use App\Models\ApplicationPlan;
use App\Models\JourneyTask;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** The stages an application goes through after it is written, and the tasks at each stage. */
class Journey
{
    /** @return array<string,array{label:string,hint:string,tasks:list<string>}> */
    public function stages(): array
    {
        return config('journey.stages');
    }

    /** The stage the student sees now: it follows the application until review passes, then it is what SAS set. */
    public function currentStage(ApplicationPlan $plan): string
    {
        return match ($plan->stage) {
            'draft', 'returned' => 'applying',
            'submitted' => 'review',
            default => $plan->journey_stage ?: 'ready',
        };
    }

    public function label(string $stage): string
    {
        return $this->stages()[$stage]['label'] ?? $stage;
    }

    /** SAS moves a passed application to a later stage. The stage's standard tasks are added once. */
    public function setStage(ApplicationPlan $plan, string $stage, User $actor): ApplicationPlan
    {
        if (! in_array($stage, config('journey.staff_stages'), true)) {
            throw new InvalidArgumentException('Unknown stage.');
        }
        if ($plan->stage !== 'passed') {
            throw new InvalidArgumentException('The journey starts once the application has passed review.');
        }
        $before = $plan->journey_stage;
        $plan->forceFill(['journey_stage' => $stage, 'journey_stage_at' => now()])->save();
        foreach ($this->stages()[$stage]['tasks'] as $title) {
            $task = JourneyTask::firstOrNew(['plan_id' => $plan->id, 'stage' => $stage, 'title' => $title]); // plan_id is guarded, so it is set explicitly
            $task->exists || $task->forceFill(['plan_id' => $plan->id, 'custom' => false])->save();
        }
        AuditLog::record('plan.journey_stage', $plan, ['stage' => $before], ['stage' => $stage], null, $actor);

        return $plan;
    }

    public function addTask(ApplicationPlan $plan, string $title, ?string $dueOn, User $actor, ?string $stage = null): JourneyTask
    {
        $task = new JourneyTask(['stage' => $stage ?: $this->currentStage($plan), 'title' => trim($title), 'due_on' => filled($dueOn) ? $dueOn : null, 'custom' => true]);
        $task->forceFill(['plan_id' => $plan->id, 'created_by' => $actor->id])->save();

        return $task;
    }

    /** Ticks or unticks a task. The student, their agent and their counsellor can all do it. */
    public function setDone(JourneyTask $task, bool $done, User $actor): JourneyTask
    {
        $task->forceFill(['done_at' => $done ? now() : null, 'done_by' => $done ? $actor->id : null])->save();

        return $task;
    }

    /** @return Collection<string,Collection<int,JourneyTask>> tasks by stage, in stage order */
    public function tasksByStage(ApplicationPlan $plan): Collection
    {
        $tasks = $plan->journeyTasks()->orderBy('due_on')->orderBy('id')->get()->groupBy('stage');

        return collect(array_keys($this->stages()))->mapWithKeys(fn ($s) => [$s => $tasks->get($s, collect())]);
    }

    /** Position of a stage in the list, so the page can show which are behind and which are ahead. */
    public function index(string $stage): int
    {
        return (int) array_search($stage, array_keys($this->stages()), true);
    }

    /** @return Collection<int,JourneyTask> overdue, not done */
    public function overdue(iterable $planIds): Collection
    {
        return JourneyTask::whereIn('plan_id', $planIds)->whereNull('done_at')->whereDate('due_on', '<', today())->with('plan.cycle.programme')->orderBy('due_on')->get();
    }
}
