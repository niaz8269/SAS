<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplyProgress;
use App\Domain\Scholarship\DeadlineService;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** One screen that answers "what do I do now?": four steps, the next one highlighted, then the student's applications and their status. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DeadlineService $deadlines, ApplyProgress $progress)
    {
        $user = $request->user();
        $p = $progress->for($user);
        $plans = $p['plans'];

        // Soonest confirmed closing dates among the scholarships the student chose.
        $upcoming = $plans->filter(fn ($plan) => in_array($plan->status, [PlanStatus::Interested, PlanStatus::Preparing, PlanStatus::ReadyForReview], true))->pluck('cycle')
            ->filter(fn ($c) => $deadlines->nextClosing($c) !== null)->sortBy(fn ($c) => $deadlines->nextClosing($c)->getTimestamp())->take(3);

        return view('student.dashboard', [
            'user' => $user, 'steps' => $p['steps'], 'current' => $p['current'], 'plans' => $plans, 'docs' => $p['docs'], 'returned' => $p['returned'],
            'upcoming' => $upcoming, 'svc' => $deadlines,
            'cases' => ServiceCase::where('user_id', $user->id)->with('invoices')->latest('id')->get(),
        ]);
    }
}
