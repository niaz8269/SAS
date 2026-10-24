# Data dictionary (R1)

The authoritative schema is `database/migrations`. This file records meaning and rules that the column types cannot.

## Identity and access
- `users`: `status` active|suspended (suspension deletes sessions). `timezone` is an IANA id. `app_authentication_secret` / `_recovery_codes` are encrypted (staff MFA).
- `user_roles`: one row per role. Permissions are derived in `config/sas.php`. Students have no role rows.
- `audits`: append-only (actor, action, subject, before/after JSON, reason, IP). Never contains document contents.

## Scholarship data
- `scholarship_programmes`: the enduring award. `scholarship_cycles`: one annual or intake-specific call (`cycle_key` unique per programme; `external_key` unique, used by imports).
- `scholarship_cycles.publication_state`: draft → in_review → changes_requested | approved → published → archived. `application_status`: upcoming | open | closed | rolling | deadline_unconfirmed | suspended. These are independent. `application_status` is recomputed every minute and at read time; `next_closing_sort` is an internal sort/filter instant, never displayed as a closing time.
- `fully_funded_verified`: only when true may a `funding_type=full` cycle be labelled "Fully funded".
- `any_nationality` vs empty `cycle_nationalities`: the first means open to all; empty with the flag off means **unconfirmed**, never "all".
- `any_subject` works like `any_nationality`: on means open to every subject; off with no `cycle_subjects` means unconfirmed. `cycle_excluded_nationalities` lists nationalities that cannot apply to a call that is otherwise open to any nationality (usually the host country's own citizens); search, the page and the student match all honour it.
- `cycle_benefits` (what is covered: tuition, living allowance, accommodation, travel, insurance, other; each covered, partial, not covered or unknown, with limits and conditions), `cycle_requirements` (required documents, optional and conditional ones marked) and `cycle_criteria` (numeric rules only) are filled from official pages by `sas:import-eligibility`. Criteria that differ by degree level or route stay in `eligibility_notes`.
- `cycle_deadlines`: append-only versions. `date_type` exact (UTC instant in `closes_at_utc` + `source_timezone`), date_only (`closes_on` calendar date, no time), rolling, not_announced, route_dependent. `superseded_at` null = current.
- `cycle_criteria`: structured, evidenced criteria used by matching (`min_gpa` with its scale in `unit`, `language` with the test name in `unit`, `age`).
- `source_evidence`: official source URL, publisher, `publisher_type` (aggregator never counts as verification), `checked_at`.
- `cycle_reviews`: every state change with actor, notes and an `override` flag.
- `correction_reports`, `imports`, `import_rows` (staged CSV rows with row numbers and errors).

## Student workspace
- `student_profiles`: everything optional; `gpa_value` + `gpa_scale` kept verbatim (never converted).
- `saved_scholarships` unique (user, cycle). `application_plans` unique (user, cycle), opaque `public_id`; `status_source` student_reported | staff_confirmed; `requirements_hash` detects changed requirements. `application_tasks` hold requirement snapshots.
- `documents` (opaque `public_id`), `document_versions` (`storage_key` is a ULID; `scan_status` pending|available|rejected; files live under `quarantine/` until `released/`), `document_associations` (explicit student-created links).

## Communication
- `notification_preferences` (category × channel, consent text/time/source), `notification_events` (`dedup_key` unique, deadline version, status), `notification_deliveries` (per-channel ledger), `notifications` (in-app).
- `enquiries` (opaque `public_id`, reference `ENQ-…`, `view_token_hash` for limited-purpose links, `duplicate_of_id` flags repeats), `enquiry_messages` (`visibility` public|internal), `consultations` (`status` requested|confirmed|…; confirmed time stored in UTC).

## Content
- `services` (price/turnaround null = "Request a quotation"), `articles` + `article_revisions`, `countries` (nationality values and destination guides), `degree_levels`, `subjects` + aliases, `universities`, `funders`, `analytics_events` (no personal data).

## Not present yet (R2/R3)
Orders, quotes, payments, invoices, refunds, availability/holds, partner organisations, case assignments and tasks, outbox.
