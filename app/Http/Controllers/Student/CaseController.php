<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** The student's view of the applications SAS supports for them: status, fees and documents. Messages live in the one conversation with their counsellor. */
class CaseController extends Controller
{
    public function index(Request $request)
    {
        $cases = ServiceCase::where('user_id', $request->user()->id)->with(['advisor', 'invoices', 'cycle.programme'])->latest('id')->get();

        return view('student.cases.index', ['cases' => $cases]);
    }

    public function show(Request $request, ServiceCase $case, ApplicationFormService $forms, Requirements $requirements)
    {
        $this->own($request, $case);
        $user = $request->user();
        $case->load(['cycle.degreeLevels', 'degreeLevel']);
        $plan = $case->cycle_id ? $user->applicationPlans()->where('cycle_id', $case->cycle_id)->first() : null;
        $docProgress = $plan ? $requirements->progress($requirements->forPlan($plan)) : null;

        return view('student.cases.show', [
            'case' => $case->load(['advisor', 'invoices', 'cycle.programme']),
            'earlierMessages' => $case->messages()->where('visibility', 'public')->with('author')->get(), // replies written here before there was one conversation; internal notes never reach the student
            'plan' => $plan, 'docProgress' => $docProgress,
            'formPercent' => $forms->percent($forms->formFor($user)),
        ]);
    }

    private function own(Request $request, ServiceCase $case): void
    {
        $this->authorize('view', $case);
        abort_unless($case->user_id === $request->user()->id, 404); // staff use the admin panel, not the student workspace
    }
}
