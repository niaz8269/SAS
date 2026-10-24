<?php

namespace App\Http\Controllers\Student;

use App\Domain\Journey\Journey;
use App\Http\Controllers\Concerns\ActsForStudent;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\JourneyTask;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** After the application is written: where it is now, what is next, and the tasks the student ticks off. */
class ApplicationJourneyController extends Controller
{
    use ActsForStudent;

    public function show(Request $request, ApplicationPlan $plan, Journey $journey)
    {
        $this->authorize('view', $plan);
        $plan->load('cycle.programme');
        $student = $this->student($request);

        return view('student.journey', [
            'plan' => $plan, 'cycle' => $plan->cycle, 'step' => 0, 'student' => $student, 'locked' => true,
            'case' => ServiceCase::where('user_id', $student->id)->where('cycle_id', $plan->cycle_id)->latest('id')->first(),
            'current' => $journey->currentStage($plan), 'stages' => $journey->stages(), 'tasks' => $journey->tasksByStage($plan), 'journey' => $journey,
        ]);
    }

    public function toggle(Request $request, ApplicationPlan $plan, JourneyTask $task, Journey $journey)
    {
        $this->authorize('update', $plan);
        abort_unless($task->plan_id === $plan->id, 404);
        $journey->setDone($task, $request->boolean('done'), $request->user());

        return redirect()->to($this->ctx($request)->route('applications.journey', $plan).'#stage-'.$task->stage)->with('status', $task->isDone() ? 'Marked as done.' : 'Marked as not done.');
    }
}
