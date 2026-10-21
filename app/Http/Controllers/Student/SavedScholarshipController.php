<?php

namespace App\Http\Controllers\Student;

use App\Domain\StudentWorkspace\PlanService;
use App\Http\Controllers\Controller;
use App\Models\ScholarshipCycle;
use Illuminate\Http\Request;

class SavedScholarshipController extends Controller
{
    public function index(Request $request)
    {
        $saved = $request->user()->savedScholarships()
            ->with(['cycle.programme.funder', 'cycle.destinations', 'cycle.degreeLevels', 'cycle.currentDeadlines', 'cycle.universities', 'cycle.programme.universities'])
            ->latest()->get();

        return view('student.saved', ['saved' => $saved]);
    }

    public function store(Request $request, PlanService $plans)
    {
        $data = $request->validate(['cycle_id' => ['required', 'integer']]);
        $cycle = ScholarshipCycle::visible()->findOrFail($data['cycle_id']); // only public cycles can be saved
        $plans->save($request->user(), $cycle);

        return back()->with('status', 'Saved to your workspace.');
    }

    public function destroy(Request $request, ScholarshipCycle $cycle, PlanService $plans)
    {
        $plans->unsave($request->user(), $cycle);

        return back()->with('status', 'Removed from your saved scholarships.');
    }
}
