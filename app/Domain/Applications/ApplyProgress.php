<?php

namespace App\Domain\Applications;

use App\Domain\Documents\Requirements;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The four steps every student sees, on Home and in the menu, with the same names and the same rule for "done":
 * 1 Your details, 2 Choose scholarships, 3 Documents, 4 Send to SAS. It only reads; the rules stay in the services it asks.
 * Worked out once per request, so the menu and the page do not repeat the queries.
 */
class ApplyProgress
{
    public function __construct(private readonly ApplicationFormService $forms, private readonly Requirements $requirements) {}

    /**
     * @return array{steps: list<array{n:int,title:string,done:bool,detail:string,url:string}>, current: ?array, plans: Collection, docs: Collection, unsent: Collection, returned: Collection}
     */
    public function for(User $student): array
    {
        // Kept on the request (not on this object), so a second request in the same process never sees stale answers.
        $attributes = request()->attributes;
        $key = 'apply_progress.'.$student->id;
        if (! $attributes->has($key)) {
            $attributes->set($key, $this->build($student));
        }

        return $attributes->get($key);
    }

    private function build(User $student): array
    {
        $plans = $student->applicationPlans()->with(['cycle.programme', 'cycle.degreeLevels', 'cycle.currentDeadlines'])->latest()->get();
        $docs = $plans->mapWithKeys(fn ($p) => [$p->id => $this->requirements->progress($this->requirements->forPlan($p))]);
        $formPercent = $this->forms->percent($this->forms->formFor($student));
        $missingPlan = $plans->first(fn ($p) => count($docs[$p->id]['missing']) > 0);
        $unsent = $plans->filter(fn ($p) => $p->isEditable())->values();
        $returned = $plans->filter(fn ($p) => $p->stage === 'returned')->values();
        $toSend = $unsent->first(fn ($p) => count($docs[$p->id]['missing']) === 0) ?? $unsent->first();

        $steps = [
            ['n' => 1, 'title' => 'Your details', 'done' => $formPercent >= 100, 'detail' => $formPercent.'% filled in', 'url' => route('student.form.show')],
            ['n' => 2, 'title' => 'Choose scholarships', 'done' => $plans->isNotEmpty(), 'detail' => $plans->isEmpty() ? 'None chosen yet' : $plans->count().' chosen', 'url' => route('student.choose.index')],
            ['n' => 3, 'title' => 'Documents', 'done' => $plans->isNotEmpty() && ! $missingPlan,
                'detail' => $plans->isEmpty() ? 'After you choose a scholarship' : ($missingPlan ? $docs->sum(fn ($d) => count($d['missing'])).' still needed' : 'All mandatory documents provided'),
                'url' => $missingPlan ? route('student.applications.materials', $missingPlan) : route('student.applications.index')],
            ['n' => 4, 'title' => 'Send to SAS', 'done' => $plans->isNotEmpty() && $unsent->isEmpty(),
                'detail' => $plans->isEmpty() ? 'After you choose a scholarship' : ($unsent->isEmpty() ? 'All sent to SAS' : $unsent->count().' not sent yet'),
                'url' => $toSend ? route('student.applications.submit', $toSend) : route('student.applications.index')],
        ];

        return [
            'steps' => $steps,
            'current' => collect($steps)->first(fn ($s) => ! $s['done']),
            'plans' => $plans, 'docs' => $docs, 'unsent' => $unsent, 'returned' => $returned,
        ];
    }
}
