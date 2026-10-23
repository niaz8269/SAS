# Decision log

Format: ID · date · decision · reason · requirement refs · estimate impact.

## Resolved (technical, reversible)

- **D-01 · 2026-10-22 · Stack verified.** `laravel/framework` 13.38.0, `filament/filament` 5.10.3, `livewire/livewire` 4.4.8,
  PHP 8.4.22. Composer resolved with security-advisory blocking enabled and reports no advisories. Exact `v5.0.0`/`v4.0.0`
  releases are blocked by advisories, so lockfile pins current patched versions. §15.
- **D-02 · Roles.** Own `user_roles` table + `App\Domain\Identity\Role` enum and a role→permission map in `config/sas.php`
  instead of a third-party package. Keeps publish / refund / role-management separately grantable (AUTH-05) with no extra
  dependency. A user can hold several roles. Students are users with no staff role.
- **D-03 · Staff MFA.** Filament 5 built-in app (TOTP) authentication is required for the admin panel; staff cannot reach
  admin resources without it enabled (AUTH-02). Student MFA is optional and deferred (listed in PROGRESS.md).
- **D-04 · Database.** SQLite for local/test, MySQL 8.4 for staging/production. Migrations avoid vendor-specific SQL.
  Keyword search uses indexed `LIKE` over title/institution/funder/aliases in R1 (spec: "database search"); a Scout adapter
  seam is `ScholarshipSearch`.
- **D-05 · Queues.** `database` queue driver locally; Redis + Horizon in production via env. Horizon is not installed in the
  local sandbox (no Redis); it is a documented production step in `docs/DEPLOYMENT.md`.
- **D-06 · Files.** Disks `public_media` and `private_documents` (local in dev, S3 in prod). Private files use ULID keys,
  start in `quarantine`, and are released only by `ScanDocumentJob`. Scanner is an adapter: `clamav` in production;
  `fake` only for tests. If the scanner is unreachable the file stays *Pending scan* and the job retries with backoff.
- **D-07 · Deadlines.** `cycle_deadlines` is append-only versioned: a reschedule supersedes the old row and bumps the version.
  Reminder rows carry the deadline version + a unique dedup key; stale pending reminders are cancelled on reschedule and
  re-checked at send time (DEAD-03, NOT-03).
- **D-08 · Laravel Boost.** The skeleton's bootstrap `AGENTS.md` asks to install `laravel/boost` via an interactive
  installer. Not part of the handoff and optional, so it is not installed. `AGENTS.md` is replaced with project notes.
- **D-09 · Imagery.** No licensed photography supplied. Hero/destination imagery is CSS/SVG artwork and gradients clearly
  marked as temporary placeholders. No testimonials, statistics or partnerships are invented.
- **D-11 · Owner-authorised publication.** The second-person verification rule normally needs two people. On 2026-10-23 the owner approved and published the 30 researched drafts alone, on their own recorded authority (administrator override with a stated reason); this is visible in each cycle's review history. Future listings should return to a second reviewer.
- **D-10 · Demo data.** Retired on 2026-10-23: the `DemoSeeder` and all "[DEMO]" records were removed at the owner's request;
  `php artisan sas:remove-demo-data` clears any leftovers. Scholarship data enters only as researched drafts (`data/seed`) that staff review.

## Open owner decisions (kept configurable / disabled — §24)

1. Domain, legal entity, launch jurisdictions, logo. (Footer shows `config('sas.legal_entity')` placeholder "To be confirmed".)
2. Service prices, packages, refund rules — services show "Request a quotation"; no prices exist anywhere.
3. Payment gateway, tax, invoicing — R2.
4. Email provider, hosting budget — mail defaults to `log`.
5. Retention periods and consent text — defaults from §19 are config values flagged "pending approval".
6. Legal pages (privacy, terms, refund) — draft placeholders that state they need owner review.
7. Support hours and accountable operator for alerts.
