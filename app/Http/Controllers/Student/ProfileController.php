<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('student.profile', [
            'user' => $user, 'profile' => $user->studentProfile()->firstOrNew(),
            'countries' => Country::orderBy('name')->get(), 'destinations' => Country::destinations()->get(),
            'degrees' => DegreeLevel::orderBy('sort')->get(), 'subjects' => Subject::orderBy('name')->get(),
        ]);
    }

    /** STU-01 / STU-02: everything optional; grades kept verbatim in their original scale. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-]{6,32}$/'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'nationality_country_id' => ['nullable', 'exists:countries,id'],
            'residence_country_id' => ['nullable', 'exists:countries,id'],
            'preferred_destination_ids' => ['nullable', 'array'],
            'preferred_destination_ids.*' => ['integer', 'exists:countries,id'],
            'desired_degree_level_id' => ['nullable', 'exists:degree_levels,id'],
            'subject_interest_ids' => ['nullable', 'array'],
            'subject_interest_ids.*' => ['integer', 'exists:subjects,id'],
            'prior_qualification' => ['nullable', 'string', 'max:190'],
            'institution' => ['nullable', 'string', 'max:190'],
            'completion_year' => ['nullable', 'integer', 'between:1950,2060'],
            'gpa_value' => ['nullable', 'string', 'max:16'],
            'gpa_scale' => ['nullable', 'string', 'max:48'],
            'language_evidence' => ['nullable', 'array', 'max:8'],
            'language_evidence.*.test' => ['nullable', 'string', 'max:48'],
            'language_evidence.*.score' => ['nullable', 'string', 'max:16'],
            'budget_note' => ['nullable', 'string', 'max:190'],
            'intake_year' => ['nullable', 'integer', 'between:2026,2040'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);

        $user = $request->user();
        $user->update(collect($data)->only(['name', 'phone', 'phone_country_code', 'timezone'])->all());

        $profile = $data;
        unset($profile['name'], $profile['phone'], $profile['phone_country_code'], $profile['timezone']);
        $profile['language_evidence'] = collect($profile['language_evidence'] ?? [])->filter(fn ($r) => filled($r['test'] ?? null) && filled($r['score'] ?? null))->values()->all();
        $user->studentProfile()->updateOrCreate([], $profile);

        return redirect()->route('student.profile.edit')->with('status', 'Profile saved.');
    }
}
