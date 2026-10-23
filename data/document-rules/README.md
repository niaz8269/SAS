# Document rules per scholarship

One JSON file per scholarship, named after its slug (`chevening-scholarships.json`). Each file says which documents SAS asks a
student for when helping with that scholarship, and where that comes from. Apply them with:

```
php artisan sas:import-document-rules --dry-run     # check only
php artisan sas:import-document-rules               # apply all files in this folder
php artisan sas:import-document-rules data/document-rules/one-file.json
```

The import is additive and safe to repeat. It updates the rules named in the file and leaves other rules alone.
Rules edited by hand in the admin are overwritten if the same document, scholarship and degree level is in a file.
**To cover a scholarship added later:** add a file here (or use "Copy rules from another scholarship" in the admin) and import.

## File format

```json
{
  "slug": "chevening-scholarships",
  "checked_on": "2026-10-24",
  "sources": ["https://www.chevening.org/scholarships/apply/"],
  "rules": [
    {"kind": "passport", "level": null, "requirement": "required", "note": "Valid passport.", "source": "https://..."},
    {"kind": "recommendation", "level": "master", "requirement": "required", "note": "Two references."}
  ],
  "new_kinds": [{"key": "writing_sample", "name": "Writing sample", "description": "..."}]
}
```

* `slug`: the scholarship programme's slug (see the admin or the scholarship page URL).
* `sources`: official pages only (funder, government, embassy or host university). At least one https URL.
* `rules[].kind`: a document type key (see list below), or a key declared in `new_kinds`.
* `rules[].level`: `null` for every degree level, or `bachelor`, `master`, `doctorate`, `postdoctoral`, `diploma`.
  A rule with a level beats the same document's rule without one.
* `rules[].requirement`: `required` (mandatory), `optional`, or `not_needed` (hidden from the student).
* `rules[].note`: shown to the student, at most 300 characters, plain English. Put conditions here
  ("only if your programme is taught in Chinese").
* `rules[].source`: optional, a more specific official page; defaults to the first of `sources`.
* Never guess. If the official pages do not say, do not add a rule for that document; the default applies.

## Document type keys

photo, passport, degree_certificate, transcript, study_plan, research_proposal, recommendation, medical, chinese_language,
english_language, language_certificate, csca, pre_admission_letter, police_clearance, guardian_documents, financial_proof,
birth_certificate, employment_letter, nomination_letter, publications, portfolio, cv, statement, other
(the admin can add more; the live list is under Service desk > Required documents).

Defaults when no rule exists: photo, passport, degree_certificate and transcript are mandatory; everything else is optional.

(Also available: test_scores, writing_sample, video_statement.)
