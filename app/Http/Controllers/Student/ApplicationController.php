<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Domain\StudentWorkspace\ApplicationReview;
use App\Domain\StudentWorkspace\PlanService;
use App\Domain\StudentWorkspace\StudyLanguages;
use App\Enums\PlanStatus;
use App\Http\Controllers\Concerns\ActsForStudent;
use App\Http\Controllers\Controller;
use App\Models\ApplicationPlan;
use App\Models\ApplicationTask;
use App\Models\DegreeLevel;
use App\Models\Document;
use App\Models\DocumentAssociation;
use App\Models\DocumentKind;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One application to one scholarship, in four steps: 1 Apply now (the scholarship, degree, language), 2 Application form,
 * 3 Upload materials, 4 Apply submit. Used by the student, and by an agent working for a student they look after.
 */
class ApplicationController extends Controller
{
    use ActsForStudent;

    public function index(Request $request, Requirements $requirements, ApplicationFormService $forms)
    {
        $student = $this->student($request);
        $list = $student->applicationPlans()->with(['cycle.programme', 'cycle.degreeLevels'])->latest()->get();
        $filter = in_array($request->query('show'), ['unfinished', 'finished'], true) ? $request->query('show') : 'all';
        $list = $list->when($filter === 'unfinished', fn ($c) => $c->filter->isEditable())->when($filter === 'finished', fn ($c) => $c->reject->isEditable())->values();

        return view('student.applications.index', [
            'plans' => $list, 'filter' => $filter, 'student' => $student,
            'docs' => $list->mapWithKeys(fn ($p) => [$p->id => $requirements->progress($requirements->forPlan($p))]),
            'formPercent' => $forms->percent($forms->formFor($student)),
            'cases' => ServiceCase::where('user_id', $student->id)->whereIn('cycle_id', $list->pluck('cycle_id'))->get()->keyBy('cycle_id'),
        ]);
    }

    public function store(Request $request, PlanService $plans)
    {
        $data = $request->validate(['cycle_id' => ['required', 'integer']]);
        $cycle = ScholarshipCycle::visible()->findOrFail($data['cycle_id']);
        $plan = $plans->create($this->student($request), $cycle);

        return redirect()->to($this->ctx($request)->route('applications.show', $plan))->with('status', 'Added to your applications. Follow the four steps to apply. Nothing is sent to the funder yet.');
    }

    /** Step 1: Apply now. Confirm the scholarship, the degree and the language of study. */
    public function show(Request $request, ApplicationPlan $plan, Requirements $requirements, StudyLanguages $languages)
    {
        $this->authorize('view', $plan); // 403 for another user's plan id (UAT-07)
        $plan->load(['cycle.programme.hostCountry', 'cycle.degreeLevels', 'cycle.destinations', 'cycle.currentDeadlines']);

        return view('student.wizard.apply', $this->base($request, $plan, 1) + [
            'languageOptions' => $languages->optionsFor($plan->cycle), 'levelId' => $requirements->effectiveLevel($plan->cycle, $plan->degree_level_id),
            'levels' => $plan->cycle->degreeLevels->isNotEmpty() ? $plan->cycle->degreeLevels : DegreeLevel::orderBy('sort')->get(),
        ]);
    }

    public function level(Request $request, ApplicationPlan $plan, CaseService $cases)
    {
        $this->authorize('update', $plan);
        abort_unless($plan->isEditable(), 403);
        $data = $request->validate(['degree_level_id' => ['nullable', 'integer', 'exists:degree_levels,id']]);
        $levelId = $data['degree_level_id'] ?? null;
        $plan->update(['degree_level_id' => $levelId]);
        foreach (ServiceCase::where('user_id', $plan->user_id)->where('cycle_id', $plan->cycle_id)->get() as $case) {
            $cases->setDegreeLevel($case, $levelId, $request->user()); // a case SAS opened for this scholarship follows the student's choice
        }

        return redirect()->to($this->ctx($request)->route('applications.show', $plan).'#level')->with('status', 'Degree saved. The documents in step 3 follow it.');
    }

    public function language(Request $request, ApplicationPlan $plan, StudyLanguages $languages)
    {
        $this->authorize('update', $plan);
        abort_unless($plan->isEditable(), 403);
        $plan->loadMissing('cycle.destinations', 'cycle.programme.hostCountry');
        $data = $request->validate(['study_language' => ['nullable', 'string', Rule::in($languages->optionsFor($plan->cycle))]]);
        $plan->update(['study_language' => $data['study_language'] ?? null]);

        return redirect()->to($this->ctx($request)->route('applications.show', $plan).'#language')->with('status', 'Language of study saved.');
    }

    /** Step 3: Upload materials. Every document the scholarship asks for gets its own slot. */
    public function materials(Request $request, ApplicationPlan $plan, Requirements $requirements)
    {
        $this->authorize('view', $plan);
        $plan->load(['cycle.programme', 'cycle.degreeLevels']);
        $rows = $requirements->forPlan($plan);

        return view('student.wizard.materials', $this->base($request, $plan, 3) + [
            'rows' => $rows, 'docProgress' => $requirements->progress($rows),
            'levelId' => $requirements->effectiveLevel($plan->cycle, $plan->degree_level_id), 'multipleLevels' => $plan->cycle->degreeLevels->count() > 1,
        ]);
    }

    /** "I cannot provide this document now" with the reason, for each document the student ticks. */
    public function reasons(Request $request, ApplicationPlan $plan)
    {
        $this->authorize('update', $plan);
        abort_unless($plan->isEditable(), 403);
        $data = $request->validate(['cannot' => ['nullable', 'array'], 'cannot.*' => ['integer'], 'reason' => ['nullable', 'array'], 'reason.*' => ['nullable', 'string', 'max:500']]);
        $kinds = DocumentKind::whereIn('id', array_keys($request->input('reason', [])))->pluck('id')->all();
        foreach ($kinds as $kindId) {
            $ticked = isset($data['cannot'][$kindId]);
            $text = trim((string) ($data['reason'][$kindId] ?? ''));
            if ($ticked && $text === '') {
                return back()->withErrors(['reason.'.$kindId => 'Please write the reason you cannot provide this document now.'])->withInput();
            }
            $ticked
                ? $plan->documentReasons()->updateOrCreate(['kind_id' => $kindId], ['reason' => $text])
                : $plan->documentReasons()->where('kind_id', $kindId)->delete();
        }

        return redirect()->to($this->ctx($request)->route('applications.materials', $plan))->with('status', 'Saved.');
    }

    /** Step 4: Apply submit. A summary of what is done and what is missing, then send to SAS for review. */
    public function submitPage(Request $request, ApplicationPlan $plan, Requirements $requirements, ApplicationFormService $forms, ApplicationReview $review)
    {
        $this->authorize('view', $plan);
        $plan->load(['cycle.programme', 'cycle.degreeLevels', 'cycle.destinations']);

        return view('student.wizard.submit', $this->base($request, $plan, 4) + ['problems' => $review->problems($plan)]);
    }

    public function submit(Request $request, ApplicationPlan $plan, ApplicationReview $review)
    {
        $this->authorize('update', $plan);
        try {
            $review->submit($plan, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to($this->ctx($request)->route('applications.submit', $plan))->with('status', 'Submitted. SAS will review the application and tell you if anything needs to be fixed.');
    }

    /** The application form as a printable page ("Preview" and "Download application form"). */
    public function preview(Request $request, ApplicationPlan $plan, ApplicationFormService $forms, Requirements $requirements)
    {
        $this->authorize('view', $plan);
        $plan->load(['cycle.programme', 'degreeLevel', 'user']);
        $rows = $requirements->forPlan($plan);

        return response()->view('student.wizard.preview', ['plan' => $plan, 'form' => $forms->formFor($plan->user), 'sections' => $forms->sections(), 'rows' => $rows->filter(fn ($r) => $r['documents']->isNotEmpty() || $r['reason'])])
            ->header('Cache-Control', 'private, no-store'); // it holds the student's personal data
    }

    /** The old tracker: checklist, deadline, status, notes and linked documents. Optional, kept for students who use it. */
    public function tools(Request $request, ApplicationPlan $plan, PlanService $plans)
    {
        $this->authorize('view', $plan);
        $plan->load(['cycle.programme', 'cycle.currentDeadlines', 'tasks']);

        return view('student.applications.tools', $this->base($request, $plan, 0) + [
            'progress' => $plans->progress($plan), 'changed' => $plans->requirementsChanged($plan),
            'documents' => $this->student($request)->documents()->with('currentVersion')->get(),
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

    public function destroy(Request $request, ApplicationPlan $plan)
    {
        $this->authorize('delete', $plan);
        abort_unless($plan->isEditable(), 403); // an application already with SAS cannot be deleted by the student or agent
        $plan->delete();

        return redirect()->to($this->ctx($request)->route('applications.index'))->with('status', 'Application deleted.');
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

    /** FILE-03: sharing/association is an explicit action on a document the student owns and a plan they own. */
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

    /** Variables every wizard page needs. */
    private function base(Request $request, ApplicationPlan $plan, int $step): array
    {
        $forms = app(ApplicationFormService::class);
        $student = $this->student($request);

        return ['plan' => $plan, 'cycle' => $plan->cycle, 'step' => $step, 'student' => $student, 'formPercent' => $forms->percent($forms->formFor($student)),
            'case' => ServiceCase::where('user_id', $student->id)->where('cycle_id', $plan->cycle_id)->latest('id')->first(), 'locked' => ! $plan->isEditable(), ];
    }
}
