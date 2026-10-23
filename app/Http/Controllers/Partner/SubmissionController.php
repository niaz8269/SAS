<?php

namespace App\Http\Controllers\Partner;

use App\Domain\Editorial\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Partner\Concerns\ResolvesOrganisation;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\PartnerSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Partners propose scholarship listings. Nothing here publishes anything: editors check each one against the official source. */
class SubmissionController extends Controller
{
    use ResolvesOrganisation;

    public function index(Request $request)
    {
        return view('partner.submissions.index', ['organisation' => $organisation = $this->organisation($request), 'submissions' => $organisation->submissions()->latest('id')->get()]);
    }

    public function create(Request $request)
    {
        $this->organisation($request);

        return view('partner.submissions.form', $this->formData(new PartnerSubmission(['funding_type' => 'unknown', 'cycle_year' => now()->year + 1])));
    }

    public function store(Request $request)
    {
        $organisation = $this->organisation($request);
        $submission = new PartnerSubmission($this->validated($request));
        $submission->forceFill(['organisation_id' => $organisation->id, 'submitted_by' => $request->user()->id, 'status' => 'submitted'])->save();
        AuditLog::record('partner.submission_created', $submission, null, ['title' => $submission->title]);

        return redirect()->route('partner.submissions.show', $submission)->with('status', 'Thank you. Our editors will check it against your official source and reply here.');
    }

    public function show(Request $request, PartnerSubmission $submission)
    {
        $this->organisation($request);
        $this->authorize('view', $submission);

        return view('partner.submissions.show', ['submission' => $submission->load(['country'])]);
    }

    public function edit(Request $request, PartnerSubmission $submission)
    {
        $this->organisation($request);
        $this->authorize('update', $submission);

        return view('partner.submissions.form', $this->formData($submission));
    }

    public function update(Request $request, PartnerSubmission $submission)
    {
        $this->organisation($request);
        $this->authorize('update', $submission);
        $before = $submission->only(['title', 'status']);
        $submission->fill($this->validated($request));
        // A corrected submission goes back to the editors.
        $submission->forceFill(['status' => 'submitted'])->save();
        AuditLog::record('partner.submission_updated', $submission, $before, ['title' => $submission->title, 'status' => 'submitted']);

        return redirect()->route('partner.submissions.show', $submission)->with('status', 'Your changes were sent to the editors.');
    }

    private function formData(PartnerSubmission $submission): array
    {
        return ['submission' => $submission, 'countries' => Country::orderBy('name')->get(['id', 'name']), 'degrees' => DegreeLevel::orderBy('id')->get(['code', 'name'])];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'funder_name' => ['nullable', 'string', 'max:190'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'degree_codes' => ['nullable', 'array'],
            'degree_codes.*' => ['string', Rule::exists('degree_levels', 'code')],
            'cycle_year' => ['nullable', 'integer', 'between:2020,2060'],
            'intake_term' => ['nullable', 'string', 'max:60'],
            'funding_type' => ['required', Rule::in(array_keys(PartnerSubmission::FUNDING))],
            'funding_summary' => ['nullable', 'string', 'max:2000'],
            'teaching_language' => ['nullable', 'string', 'max:80'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'eligibility' => ['nullable', 'string', 'max:4000'],
            'deadline_on' => ['nullable', 'date_format:Y-m-d'],
            'deadline_note' => ['nullable', 'string', 'max:300'],
            'official_url' => ['required', 'string', 'max:500', 'regex:~^https?://[^\s]+$~i'],
        ], ['official_url.regex' => 'Give the full official web address, starting with https://.', 'official_url.required' => 'The official page that states these details is required.']);
        $data['degree_codes'] = array_values($data['degree_codes'] ?? []);

        return $data;
    }
}
