<?php

namespace App\Domain\StudentWorkspace;

use App\Domain\Communication\ReminderScheduler;
use App\Domain\Editorial\AuditLog;
use App\Enums\PlanStatus as P;
use App\Models\AnalyticsEvent;
use App\Models\ApplicationPlan;
use App\Models\SavedScholarship;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * APP-01 / APP-02 / STU-03. A plan is the student's private tracker. Nothing here ever contacts an external portal,
 * and "Submitted" is always labelled as reported by the student.
 */
class PlanService
{
    /** @var array<string,list<P>> */
    private const NEXT = [
        'interested' => [P::Preparing, P::ReadyForReview, P::Submitted, P::Withdrawn],
        'preparing' => [P::Interested, P::ReadyForReview, P::Submitted, P::Withdrawn],
        'ready_for_review' => [P::Interested, P::Preparing, P::Submitted, P::Withdrawn],
        'submitted' => [P::AwaitingOutcome, P::Withdrawn],
        'awaiting_outcome' => [P::Awarded, P::Unsuccessful, P::Withdrawn],
        'awarded' => [], 'unsuccessful' => [], 'withdrawn' => [],
    ];

    public function __construct(private ReminderScheduler $reminders) {}

    public function save(User $user, ScholarshipCycle $cycle): SavedScholarship
    {
        // Saving does not create an application and does not enable marketing consent (STU-03).
        $saved = SavedScholarship::firstOrCreate(['user_id' => $user->id, 'cycle_id' => $cycle->id]);
        if ($saved->wasRecentlyCreated) {
            AnalyticsEvent::track('scholarship_saved', $cycle->id);
        }
        $this->reminders->syncUserCycle($user, $cycle);

        return $saved;
    }

    public function unsave(User $user, ScholarshipCycle $cycle): void
    {
        SavedScholarship::where(['user_id' => $user->id, 'cycle_id' => $cycle->id])->delete();
        $this->reminders->syncUserCycle($user, $cycle);
    }

    public function create(User $user, ScholarshipCycle $cycle): ApplicationPlan
    {
        return DB::transaction(function () use ($user, $cycle) {
            $existing = ApplicationPlan::where(['user_id' => $user->id, 'cycle_id' => $cycle->id])->first();
            if ($existing) {
                return $existing;
            }
            $cycle->load('requirements');
            $plan = new ApplicationPlan(['cycle_id' => $cycle->id, 'status' => P::Interested->value, 'requirements_hash' => $cycle->requirementsHash()]);
            $plan->user_id = $user->id;
            $plan->save();

            // APP-02: checklist is a snapshot of the requirements at this moment.
            foreach ($cycle->requirements as $r) {
                $plan->tasks()->create(['requirement_id' => $r->id, 'title' => Str::limit($r->text, 180), 'snapshot_text' => $r->text, 'is_custom' => false]);
            }
            AuditLog::record('plan.created', $plan, null, ['cycle_id' => $cycle->id], null, $user);
            AnalyticsEvent::track('plan_created', $cycle->id);
            $this->reminders->syncUserCycle($user, $cycle);

            return $plan;
        });
    }

    public function requirementsChanged(ApplicationPlan $plan): bool
    {
        return $plan->requirements_hash !== null && $plan->requirements_hash !== $plan->cycle->loadMissing('requirements')->requirementsHash();
    }

    /** Adds tasks for new requirements and flags changed ones; never overwrites completed or custom work. */
    public function acknowledgeRequirementChanges(ApplicationPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $cycle = $plan->cycle->load('requirements');
            $byRequirement = $plan->tasks()->whereNotNull('requirement_id')->get()->keyBy('requirement_id');

            foreach ($cycle->requirements as $r) {
                $task = $byRequirement->get($r->id);
                if (! $task) {
                    $plan->tasks()->create(['requirement_id' => $r->id, 'title' => Str::limit($r->text, 180), 'snapshot_text' => $r->text, 'requirement_updated' => true]);
                } elseif ($task->snapshot_text !== $r->text) {
                    $task->update(['requirement_updated' => true, 'snapshot_text' => $r->text]); // completion state is kept
                }
            }
            // Requirements that were removed upstream stay as the student's own history, flagged for review.
            $plan->tasks()->whereNotNull('requirement_id')->whereNotIn('requirement_id', $cycle->requirements->pluck('id'))->update(['requirement_updated' => true]);

            $plan->update(['requirements_hash' => $cycle->requirementsHash(), 'requirements_changed_at' => now(), 'version' => $plan->version + 1]);
        });
    }

    /** @param array{submitted_at?:?string,submission_reference?:?string,evidence_note?:?string,confirm_submitted?:mixed} $data */
    public function transition(ApplicationPlan $plan, P $to, array $data = []): ApplicationPlan
    {
        $allowed = self::NEXT[$plan->status->value] ?? [];
        if ($plan->status === $to) {
            return $plan;
        }
        if (! in_array($to, $allowed, true)) {
            abort(409, "A plan that is '{$plan->status->label()}' cannot move to '{$to->label()}'.");
        }

        if ($to === P::Submitted) {
            // APP-01: requires the student's confirmation and a submitted timestamp; evidence is optional.
            if (! filter_var($data['confirm_submitted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages(['confirm_submitted' => 'Confirm that you submitted this application on the official website.']);
            }
            $plan->submitted_at = ! empty($data['submitted_at']) ? CarbonImmutable::parse($data['submitted_at']) : now();
            $plan->submission_reference = $data['submission_reference'] ?? null;
            $plan->evidence_note = $data['evidence_note'] ?? null;
            $plan->status_source = 'student_reported';
        }

        $from = $plan->status;
        $plan->status = $to;
        $plan->version++;
        $plan->save();
        AuditLog::record('plan.status', $plan, ['status' => $from->value], ['status' => $to->value, 'source' => $plan->status_source], null, $plan->user);

        // Reminders only make sense while the application is still being prepared.
        $this->reminders->syncUserCycle($plan->user, $plan->cycle);

        return $plan;
    }

    public function progress(ApplicationPlan $plan): array
    {
        $tasks = $plan->relationLoaded('tasks') ? $plan->tasks : $plan->tasks()->get();
        $done = $tasks->whereNotNull('completed_at')->count();

        return ['done' => $done, 'total' => $tasks->count(), 'percent' => $tasks->count() ? (int) round($done / $tasks->count() * 100) : 0];
    }
}
