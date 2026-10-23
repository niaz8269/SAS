<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\StudentWorkspace\PlanService;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\ApplicationTask;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentAssociation;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationController extends Controller
{
    public function index(Request $request, PlanService $plans)
    {
        $list = $request->user()->applicationPlans()->with(['cycle.programme', 'cycle.currentDeadlines', 'tasks'])->latest()->get();

        $supported = ServiceCase::where('user_id', $request->user()->id)->whereIn('cycle_id', $list->pluck('cycle_id'))->get()->keyBy('cycle_id');

        return view('student.applications.index', ['plans' => $list, 'service' => $plans, 'supported' => $supported]);
    }

    public function store(Request $request, PlanService $plans)
    {
        $data = $request->validate(['cycle_id' => ['required', 'integer']]);
        $cycle = ScholarshipCycle::visible()->findOrFail($data['cycle_id']);
        $plan = $plans->create($request->user(), $cycle);

        return redirect()->route('student.applications.show', $plan)->with('status', 'Added to your applications. The documents this scholarship needs are listed below. Nothing has been submitted anywhere.');
    }

    public function level(Request $request, ApplicationPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $request->validate(['degree_level_id' => ['nullable', 'integer', 'exists:degree_levels,id']]);
        $plan->update(['degree_level_id' => $data['degree_level_id'] ?? null]);

        return redirect()->to(route('student.applications.show', $plan).'#checklist')->with('status', 'Degree level saved. The document list follows it.');
    }

    public function show(Request $request, ApplicationPlan $plan, PlanService $plans, Requirements $requirements, ApplicationFormService $forms)
    {
        $this->authorize('view', $plan); // 403 for another user's plan id (UAT-07)
        $plan->load(['cycle.programme', 'cycle.currentDeadlines', 'cycle.routes', 'cycle.degreeLevels', 'tasks']);
        $rows = $requirements->forScholarship($plan->cycle, $plan->degree_level_id, $request->user()->id);

        return view('student.applications.show', [
            'formPercent' => $forms->percent($forms->formFor($request->user())),
            'rows' => $rows, 'docProgress' => $requirements->progress($rows), 'levelId' => $requirements->effectiveLevel($plan->cycle, $plan->degree_level_id),
            'levels' => $plan->cycle->degreeLevels->isNotEmpty() ? $plan->cycle->degreeLevels : DegreeLevel::orderBy('sort')->get(),
            'plan' => $plan, 'progress' => $plans->progress($plan), 'changed' => $plans->requirementsChanged($plan),
            'case' => ServiceCase::where('user_id', $request->user()->id)->where('cycle_id', $plan->cycle_id)->latest('id')->first(),
            'documents' => $request->user()->documents()->with('currentVersion')->get(),
            'attached' => DocumentAssociation::where('associable_type', $plan->getMorphClass())->where('associable_id', $plan->id)->with('document.currentVersion')->get(),
        ]);
    }

    public function update(Request $request, ApplicationPlan $plan, PlanService $plans)
    {
        $this->authorize('update', $plan);
        $data = $request->validate([
            'status' => ['required', Rule::enum(PlanStatus::class)],
            'confirm_submitted' => ['nullable', 'boolean'],
            'submitted_at' => ['nullable', 'date', 'before_or_equal:now'],
            'submission_reference' => ['nullable', 'string', 'max:190'],
            'evidence_note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
        if (array_key_exists('notes', $data)) {
            $plan->update(['notes' => $data['notes']]);
        }
        $plans->transition($plan, PlanStatus::from($data['status']), $data);

        return back()->with('status', 'Plan updated.');
    }

    public function acknowledge(ApplicationPlan $plan, PlanService $plans)
    {
        $this->authorize('update', $plan);
        $plans->acknowledgeRequirementChanges($plan);

        return back()->with('status', 'Checklist updated with the latest requirements. Completed items were kept.');
    }

    public function destroy(ApplicationPlan $plan)
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return redirect()->route('student.applications.index')->with('status', 'Plan deleted.');
    }

    public function storeTask(Request $request, ApplicationPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'due_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $plan->tasks()->create($data + ['is_custom' => true]);

        return back()->with('status', 'Task added.');
    }

    public function updateTask(Request $request, ApplicationPlan $plan, ApplicationTask $task)
    {
        $this->authorize('update', $plan);
        abort_unless($task->plan_id === $plan->id, 404);
        $data = $request->validate(['completed' => ['nullable', 'boolean'], 'due_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $task->fill(collect($data)->only(['due_at', 'notes'])->all());
        if (array_key_exists('completed', $data)) {
            $task->completed_at = $data['completed'] ? now() : null;
        }
        $task->requirement_updated = false; // seen
        $task->save();

        return back();
    }

    public function destroyTask(ApplicationPlan $plan, ApplicationTask $task)
    {
        $this->authorize('update', $plan);
        abort_unless($task->plan_id === $plan->id && $task->is_custom, 404);
        $task->delete();

        return back()->with('status', 'Task removed.');
    }

    /** FILE-03: sharing/association is an explicit student action on a document they own and a plan they own. */
    public function attachDocument(Request $request, ApplicationPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $request->validate(['document' => ['required', 'string']]);
        $document = Document::where('public_id', $data['document'])->firstOrFail();
        $this->authorize('view', $document);

        DocumentAssociation::firstOrCreate(
            ['document_id' => $document->id, 'associable_type' => $plan->getMorphClass(), 'associable_id' => $plan->id],
            ['associated_by' => $request->user()->id],
        );

        return back()->with('status', 'Document linked to this plan.');
    }

    public function detachDocument(Request $request, ApplicationPlan $plan, Document $document)
    {
        $this->authorize('update', $plan);
        $this->authorize('view', $document);
        DocumentAssociation::where(['document_id' => $document->id, 'associable_type' => $plan->getMorphClass(), 'associable_id' => $plan->id])->delete();

        return back()->with('status', 'Document unlinked.');
    }
}
