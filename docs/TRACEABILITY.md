# Requirement traceability (R1)

Status: **Done** = implemented and covered by an executed automated test; **Partial** = implemented, with a stated gap; **Not done** = not implemented. R2/R3 items are out of scope for this release and not listed except where R1 touches them.

| ID | Status | Implementation | Tests / gap |
|---|---|---|---|
| CORE-01 browse free, sign-in for persistence | Done | public routes; `student.*` behind auth | `DirectoryTest`, `AuthAndIsolationTest` |
| CORE-02 three distinct actions | Done | `scholarships/show.blade.php` | `PublicPagesTest::…three_distinct_actions` |
| CORE-03/05 no guarantees, no portal passwords | Done (content) | copy on pages, `legal` drafts | manual review |
| CORE-04 sponsored labelled | Done | `sponsored`/`sponsored_label`, card + detail badge | admin test covers permission gate |
| AUTH-01 email/password, verification, reset, logout | Done | `Auth\*Controller` | `AuthAndIsolationTest` |
| AUTH-02 staff MFA, rate limits, session revoke | Done | Filament required TOTP; optional student TOTP + recovery codes; challenge applied to any account with an authenticator (so staff cannot bypass it via the site login); limiters; `User::suspend` | `AdminPanelTest`, `PrivacyAndMfaTest` |
| AUTH-03 policies on every object | Done | policies + route keys + Filament scoping | `AuthAndIsolationTest`, `DocumentTest`, `AdminPanelTest` |
| AUTH-04 assignment-scoped access | Done (enquiries) | `EnquiryPolicy`, resource query scope | `test_enquiry_policy_…`, advisor scoping test. Case-file access is R2 |
| AUTH-05 separate publish/refund/role permissions; no escalation | Done (R1 perms) | `config/sas.php`, guarded attributes | registration/profile escalation tests, role tests |
| PUB-01/02 nav and homepage order, admin-managed | Done | `layouts/public`, `home.blade.php`, flags in admin | `PublicPagesTest`; visual check of desktop only |
| PUB-03 search in URL | Done | home form → `/scholarships?…`; Livewire `#[Url]` | `DirectoryTest::…shared_link…` |
| PUB-04 country pages with sources/date | Done | `destinations/show` | `PublicPagesTest::…destination…` |
| PUB-05 service pages, no invented prices | Done | `services/show`; seeder drafts unpublished | `PublicPagesTest::…quotation` |
| PUB-06 enquiry persistence, queued mail, anti-spam, reference | Done | `EnquiryController` | `EnquiryTest` |
| PUB-07 footer legal/contact/corrections | Partial | footer + draft legal pages | legal text is a **draft awaiting owner review** |
| DIR-01..03 filters, search, sort, default view | Done | `ScholarshipSearch`, `ScholarshipDirectory` | `DirectoryTest` |
| DIR-04 card content, funded label rule | Done | `cycle-card`, `fundingLabel()` | `PublicPagesTest` |
| DIR-05/06/07 detail, unknowns, route deadlines | Done | `scholarships/show` | `PublicPagesTest` |
| DATA-01 programme vs cycle | Done | schema, importer | `ImportTest` (new year → new cycle) |
| DATA-02 evidence required, aggregator insufficient | Done | `CycleWorkflow` | `WorkflowTest` |
| DATA-03 workflow, no self-approval, override with reason | Done | `CycleWorkflow` + admin buttons | `WorkflowTest`, `AdminPanelTest` |
| DATA-04 review cadence, needs recheck | Done | `scheduleReview`, `needsRecheck`, promotion filter | `PublicPagesTest`, workflow test |
| DEAD-01..04 deadlines | Done | `DeadlineService`, `ChangeDeadline` | `DeadlineTest` |
| STU-01/02 profile, scales not converted | Done | `ProfileController`, `MatchingService` | `PlanTest`, `MatchingTest` |
| STU-03 saved unique, separate from consent | Done | `PlanService::save` | `PlanTest` |
| APP-01/02 plan states, checklist snapshot, requirements prompt | Done | `PlanService` | `PlanTest` |
| FILE-01/02/03 upload validation, quarantine, scan, opaque keys | Done (with fake scanner) | `DocumentService`, `ScanDocumentJob` | `DocumentTest`. **ClamAV adapter never run against a real clamd** |
| MATCH-01/02 rule indications | Done | `MatchingService` | `MatchingTest` |
| NOT-01/02/03 reminders, preferences, retry safety | Done | `ReminderScheduler`, `SendReminderEvent`, `Preferences` | `ReminderJobTest`. Provider bounce/suppression handling not built |
| COM-01 enquiry threads, internal notes hidden | Done | `EnquiryController`, admin messages | `EnquiryTest`, `AdminPanelTest` |
| SVC-01 catalogue | Done | `Service`, admin, seeder | — |
| SVC-02..05 writing/evaluation/attestation/visa case workflows | Not done (R2) | enquiry-led only | `required_inputs` documented on service pages |
| BOOK-01 consultation request → staff confirm | Done | `ConsultationController`, admin confirm action | `EnquiryTest`, `AdminPanelTest` |
| ORD-01 enquiry lifecycle | Done | `EnquiryStatus`, admin | `AdminPanelTest`. Quotes (`Quote issued` status is manual) are R2 |
| ADM-01 dashboard | Partial | `OperationsOverview` (counts) | no "overdue service tasks" or finance (R2); not browser-checked |
| ADM-02 content administration | Partial | Filament resources for taxonomy, programmes, cycles, services, articles | navigation / homepage placement are flags, no free-form menu editor |
| ADM-03 sanitised rich text, revisions, image alt | Done | `App\Support\Html`, `ArticleResource` | `PublicPagesTest` |
| IMP-01..03 CSV import | Done | `ScholarshipImporter` + admin | `ImportTest`. Runs synchronously (≤5,000 rows); no queued progress bar |
| SEC-01 headers, CSRF, hashing | Partial | `SecurityHeaders`, Laravel defaults | **no CSP**, see DEPLOYMENT.md |
| SEC-03 private downloads, 5 min links | Partial | policy-checked stream (local) / S3 temporary URL | S3 path not exercised |
| SEC-04 SSRF | Done by construction | importer never fetches URLs; `safeUrl` validation | `ImportTest::test_dangerous_urls…` |
| PRIV-01 data inventory/minimal logging | Partial | audits exclude contents | no formal inventory document |
| PRIV-02 export and deletion requests | Done | `AccountPrivacy`, `Student\PrivacyController` (password re-auth, emailed single-use 24 h link, typed confirmation) | `PrivacyAndMfaTest`. Retention periods for financial records are an R2/owner matter |
| PRIV-03 retention | Partial | `sas:retention-cleanup` (disabled until `retention.approved`) | only rejected-upload purge |
| PRIV-04 deletion replay | Done | tombstones (keyed email hash only) + `php artisan sas:replay-deletions` | `PrivacyAndMfaTest::test_restoring_a_backup…`. Not yet exercised with a real backup restore |
| NFR-02 load | **Not tested** | — | no 10k/50k dataset run |
| NFR-03 web vitals | **Not measured** | — | |
| A11Y-01 | Partial | semantic markup, labels, skip link, focus styles, aria-live | automated axe pass at 360/768/1280 px is clean; **manual keyboard and screen-reader audit not done** |
| SEO-01/02 | Partial | titles, canonical, sitemap, robots, noindex, breadcrumbs JSON-LD | sitemap cached 10 min; no OG images |
| ANA-01 | Partial | search-zero, view, official-link, save, plan, enquiry, consultation events (no PII) | `AnalyticsTest`. No reporting dashboard or consent banner yet |
| OPS-01..07 | Partial | docs + CI file | not executed on infrastructure |
| UAT-01..10, 14(15) | Covered by automated tests where feasible | see tests | UAT-11..13, 16: R2/ops |
