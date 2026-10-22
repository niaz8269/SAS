# Progress

Last updated: 2026-10-22. Release in scope: **R1**.

## What works (executed and verified)

- Stack verified in a clean install: Laravel 13.38, PHP 8.4.22, Livewire 4.4.8, Filament 5.10.3, Tailwind 4, Vite 8. `composer audit` and `npm audit` report no vulnerabilities.
- **Public site**: homepage (hero search, degree browse, featured, verified closing-soon, destinations, services, how it works, resources, consultation CTA), Livewire directory with URL-bound filters, scholarship detail with three distinct actions, destinations, services, resources, about, partners, contact, consultation request, correction reports, legal draft pages, sitemap, robots.
- **Scholarship domain**: programme/cycle split, routes, versioned deadlines (exact/date-only/rolling/not-announced/route-dependent), evidence, editorial workflow with separation of duties, CSV importer (staged, validated, idempotent, never publishes), correction-driven re-review.
- **Student workspace**: registration with email verification, profile (grades kept in original scale), saved scholarships, application plans with checklist snapshots and "requirements updated" prompt, student-reported submission, private document vault (quarantine, scan, opaque keys, owner-only download), notification preferences, deadline reminders (30/14/7/1 days), enquiry list.
- **Staff admin (Filament, `/admin`)**: required authenticator-app MFA, role-based navigation, cycles with workflow buttons, deadline history/change action, sources/benefits/requirements/criteria, enquiries (assignment-scoped, public replies vs internal notes), consultation confirmation, services, articles, destinations, taxonomies, imports, users/roles, audit log, operations dashboard.
- **Automated tests: 171 tests, 851 assertions, all passing** (`php vendor/bin/phpunit`, run 2026-10-22). Pint applied; `phpstan` level 1 clean; asset build succeeds.
- Homepage rendered with headless Edge at 1440 px and compared with the mockup direction (forest green / ivory / lime): layout matches in structure. Real data is demo-only.

## Not done, not verified, or limited (read this before relying on the build)

1. **R2/R3 not built** (by design): payments, orders, quotes, slot booking, partner portal, feeds.
2. ~~PRIV-02/04~~ **Built 2026-10-23**: account export, verified erasure, tombstones and `sas:replay-deletions`. Not yet run against a real backup restore. Self-service erasure is refused for staff accounts.
3. ~~Student MFA~~ **Built 2026-10-23** (optional TOTP + recovery codes at `/student/security`).
4. **Never run against real infrastructure**: MySQL 8.4, Redis/Horizon, S3 buckets, a real ClamAV daemon, a real mail provider. The ClamAV adapter and S3 download path are untested; tests use the fake scanner and a fake disk. Horizon is not installed.
5. **Accessibility/responsive (2026-10-23, automated):** axe-core 4 (WCAG 2.0/2.1/2.2 A+AA and best-practice rules) run in headless Edge against 19 public and student pages at 360, 768 and 1280 px: no violations and no horizontal overflow after fixes. Fixes: a broken QR image on the security page (it printed a data URI as text and overflowed), small status checkboxes, missing landmarks on login/register. Not covered: the Filament admin, interactive states after user input, manual keyboard and screen-reader testing, real devices. **Not measured**: NFR-02 load test (10k programmes/50k cycles), web vitals, WCAG audit (no axe/screen-reader/keyboard pass), restore drill. Responsive behaviour was only visually inspected at desktop width; a 390 px capture could not be assessed reliably (headless Edge enforces a minimum window width), so **360/390/768/1024 px are unverified**. The Filament admin was exercised through Livewire tests, not looked at in a browser. The built-in browser pane refused to open localhost, so no interactive browser session was possible.
6. **No Content-Security-Policy** (Alpine/Livewire need a plan); see DEPLOYMENT.md.
7. **Content**: no real scholarship, destination, article or service content exists. Demo records are fictional and `[DEMO]`-labelled. Service pages are seeded as unpublished drafts. Legal pages are drafts. The 50-cycle / 5-destination / 8-service-page / 5-guide launch baseline is an editorial task, not code.
8. **Staged edits (built 2026-10-24)**: changes to the main fields of a live cycle (funding, eligibility, summary, destinations, nationalities, degrees, subjects, institutions) are staged as a pending revision and need a different reviewer; stale proposals are marked outdated. **Limits:** routes, requirements, benefits, criteria, sources and deadlines are still changed directly by reviewers (audited, deadlines are versioned) and editors cannot touch them on live cycles; there is no side-by-side preview of the public page.
9. **Static analysis** is level 1. Level 2 reports 74 findings, triaged 2026-10-23: all are Larastan not inferring enum casts and untyped relation generics (no real defects). Raising the level needs PHPDoc generics on ~40 models.
10. Importer runs synchronously (limit 5,000 rows); no progress UI. Analytics records search-zero, detail view, official-link click, save, plan, enquiry and consultation events (no personal data); there is no reporting UI yet. Email bounce/suppression handling is not built.
11. `docs/TRACEABILITY.md` lists per-requirement status.

## Open owner decisions (see DECISIONS.md)

Domain/entity/jurisdictions, prices and refund terms, gateway/tax (R2), email provider, hosting budget, retention periods and consent text, legal text, support hours, imagery licences.

## Next steps

1. Owner decisions above; supply licensed imagery and real content (editorial team through the admin and CSV template).
3. Stand up staging (MySQL, Redis + Horizon, S3, ClamAV, mail), run the suite there, add Horizon, perform load test, accessibility audit (axe + manual), restore drill, then UAT.
4. Add a CSP in report-only mode; triage Larastan level 2+.
5. R2 planning once the gateway and service packages are decided.

## Environment notes

Composer 2.10 (phar in `C:\Users\82335\Projects\tools`), Node 24, Git 2.55 were installed this session. PHP comes from XAMPP (`C:\xampp\php`); add it to PATH before running artisan. Quick test runner: `C:\Users\82335\Projects\tools\t.ps1 [-Filter name]`.
