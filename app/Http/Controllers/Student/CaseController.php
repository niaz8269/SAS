<?php

namespace App\Http\Controllers\Student;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Cases\CaseService;
use App\Domain\Documents\Requirements;
use App\Http\Controllers\Controller;
use App\Models\DegreeLevel;
use App\Models\ServiceCase;
use Illuminate\Http\Request;

/** The student's view of the applications SAS supports for them: status, fees, messages and the documents they choose to share. */
class CaseController extends Controller
{
    public const CONSENT = 'I agree that SAS staff handling this case may open the documents I have ticked, only to prepare my application. I can stop sharing at any time.';

    public const FORM_CONSENT = 'I agree that SAS staff handling this case may read my application form, only to prepare my application. I can stop sharing at any time.';

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
        $rows = $requirements->forCase($case);

        return view('student.cases.show', [
            'case' => $case->load(['advisor', 'invoices', 'cycle.programme']),
            'messages' => $case->messages()->where('visibility', 'public')->with('author')->get(), // internal notes never reach the student
            'documents' => $user->documents()->with('currentVersion')->latest()->get(),
            'sharedIds' => $case->shares()->pluck('document_id')->all(),
            'plan' => $case->cycle_id ? $user->applicationPlans()->where('cycle_id', $case->cycle_id)->first() : null,
            'consent' => self::CONSENT,
            'formConsent' => self::FORM_CONSENT,
            'formPercent' => $forms->percent($forms->formFor($user)),
            'rows' => $rows, 'docProgress' => $requirements->progress($rows),
            'levels' => $case->cycle ? ($case->cycle->degreeLevels->isNotEmpty() ? $case->cycle->degreeLevels : DegreeLevel::orderBy('sort')->get()) : DegreeLevel::orderBy('sort')->get(),
        ]);
    }

    public function level(Request $request, ServiceCase $case, CaseService $cases)
    {
        $this->own($request, $case);
        $data = $request->validate(['degree_level_id' => ['nullable', 'integer', 'exists:degree_levels,id']]);
        $cases->setDegreeLevel($case, $data['degree_level_id'] ?? null, $request->user());

        return redirect()->route('student.cases.show', $case)->withFragment('checklist')->with('status', 'Degree level saved. The document list below follows it.');
    }

    public function shareForm(Request $request, ServiceCase $case, CaseService $cases)
    {
        $this->own($request, $case);
        $share = $request->boolean('share');
        $cases->shareForm($case, $request->user(), $share, self::FORM_CONSENT);

        return redirect()->route('student.cases.show', $case)->withFragment('form')->with('status', $share ? 'Your counsellor can now read your application form.' : 'Your application form is no longer shared.');
    }

    public function message(Request $request, ServiceCase $case, CaseService $cases)
    {
        $this->own($request, $case);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $cases->postMessage($case, $request->user(), $data['body']);

        return redirect()->route('student.cases.show', $case)->withFragment('messages')->with('status', 'Message sent. Your counsellor will reply here.');
    }

    public function share(Request $request, ServiceCase $case, CaseService $cases)
    {
        $this->own($request, $case);
        $data = $request->validate(['documents' => ['nullable', 'array'], 'documents.*' => ['integer']]); // consent is checked below, and only when something is shared
        $ids = $data['documents'] ?? [];
        if ($ids && ! $request->boolean('consent')) {
            return back()->withErrors(['consent' => 'Please tick the consent box to share documents.'])->withInput();
        }
        $cases->syncShares($case, $request->user(), array_map('intval', $ids), self::CONSENT);

        return redirect()->route('student.cases.show', $case)->withFragment('share')->with('status', $ids ? 'Sharing updated. Only the documents you ticked are open to your counsellor.' : 'Nothing is shared now.');
    }

    private function own(Request $request, ServiceCase $case): void
    {
        $this->authorize('view', $case);
        abort_unless($case->user_id === $request->user()->id, 404); // staff use the admin panel, not the student workspace
    }
}
