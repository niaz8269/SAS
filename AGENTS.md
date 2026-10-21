# Notes for coding agents

- Read `docs/PROGRESS.md` first; it says what is done and what is not. Business rules come from `Requirements.md` (requirement IDs are cited in code comments and `docs/TRACEABILITY.md`).
- Keep rules in `app/Domain/*` actions/services. Controllers, Livewire components, Filament pages and jobs call them; do not duplicate rules in UI code.
- Brand spelling is intentionally "Scholorship". Do not "fix" it.
- Never invent scholarship data, prices, testimonials, statistics or partnerships. Unknown stays "Not confirmed".
- Never display a closing time for a date-only deadline; only `exact` deadlines have an instant and a countdown.
- Models guard sensitive columns (`user_id`, `status`, `owner_id`, roles). Set them explicitly in trusted code (`forceFill` or relations), never from request data.
- Filament closures are resolved by parameter NAME: name the query parameter `$query` and the form data `$data`.
- Windows/PowerShell 5.1: write files with UTF-8 *without* BOM (a BOM before `<?php` breaks PHP). `Get-Content` without `-Encoding utf8` corrupts non-ASCII text.
- Tests: `php vendor/bin/phpunit`. Do not report a test as passing unless it was run.
