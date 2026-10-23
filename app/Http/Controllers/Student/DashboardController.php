<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** One screen that answers "what do I do now?": three steps, the next one highlighted, then the student's applications. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DeadlineService $deadlines, ApplicationFormService $forms, Requirements $requirements)
    {
        $user = $request->user();

        $plans = $user->applicationPlans()->with(['cycle.programme', 'cycle.degreeLevels', 'cycle.currentDeadlines'])->latest()->get();
        $docs = $plans->mapWithKeys(fn ($p) => [$p->id => $requirements->progress($requirements->forScholarship($p->cycle, $p->degree_level_id, $user->id))]);
        $formPercent = $forms->percent($forms->formFor($user));
        $missingPlan = $plans->first(fn ($p) => count($docs[$p->id]['missing']) > 0);

        $steps = [
            ['n' => 1, 'title' => 'Your details', 'done' => $formPercent >= 100, 'detail' => $formPercent.'% filled in', 'url' => route('student.form.show')],
            ['n' => 2, 'title' => 'Choose scholarships', 'done' => $plans->isNotEmpty(), 'detail' => $plans->isEmpty() ? 'None chosen yet' : $plans->count().' chosen', 'url' => route('student.choose.index')],
            ['n' => 3, 'title' => 'Upload documents', 'done' => $plans->isNotEmpty() && ! $missingPlan, 'detail' => $plans->isEmpty() ? 'After step 2' : ($missingPlan ? $docs->sum(fn ($d) => count($d['missing'])).' still needed' : 'All mandatory documents provided'), 'url' => $missingPlan ? route('student.applications.show', $missingPlan) : route('student.applications.index')],
        ];
        $current = collect($steps)->first(fn ($s) => ! $s['done']);

        // Soonest confirmed closing dates among the scholarships the student chose.
        $upcoming = $plans->filter(fn ($p) => in_array($p->status, [PlanStatus::Interested, PlanStatus::Preparing, PlanStatus::ReadyForReview], true))->pluck('cycle')
            ->filter(fn ($c) => $deadlines->nextClosing($c) !== null)->sortBy(fn ($c) => $deadlines->nextClosing($c)->getTimestamp())->take(3);

        return view('student.dashboard', [
            'user' => $user, 'steps' => $steps, 'current' => $current, 'plans' => $plans, 'docs' => $docs, 'upcoming' => $upcoming, 'svc' => $deadlines,
            'cases' => ServiceCase::where('user_id', $user->id)->with('invoices')->latest('id')->get(),
        ]);
    }
}
