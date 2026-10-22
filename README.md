# SAS – Scholorship Application Services

Laravel 13 / PHP 8.4 / Livewire 4 / Filament 5 / Tailwind 4 application for scholarship discovery, student application planning and enquiry-led support services. This repository implements **release R1** of `Requirements.md` (the brand spelling is the owner's).

Start with [docs/PROGRESS.md](docs/PROGRESS.md) (what works, what was tested, what is missing), then [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md) and [docs/DECISIONS.md](docs/DECISIONS.md).

## Run it locally (Windows)

```powershell
.\scripts\dev.ps1 -Fresh     # migrate + fictional demo data, then start server, queue worker and scheduler
```

Open http://127.0.0.1:8000. Staff admin is at `/admin` (`demo-reviewer@example.test` / `demo-password-123`; set up an authenticator app on first login). Student demo: `demo-student@example.test`. Mail goes to `storage/logs/laravel.log`. The script sets `SAS_SCANNER=fake` in your local `.env` so uploads are released locally; never use that outside local development.

## Local setup (manual)

Requirements: PHP 8.4 (extensions: mbstring, intl, pdo_sqlite or pdo_mysql, zip, fileinfo, gd, bcmath), Composer 2, Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # reference data + draft service catalogue (production-safe)
npm install && npm run build
php artisan serve
```

Demonstration records (fictional, every title prefixed `[DEMO]`; refuses to run in production):

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

Demo logins use `demo-<role>@example.test` / `demo-password-123`. Staff must set up an authenticator app on first sign-in at `/admin`.

Create a real staff account (random password + reset link; MFA enforced at first login):

```bash
php artisan sas:make-staff you@example.org "Your Name" super_admin
```

Run the scheduler and a queue worker (reminders, scans, mail):

```bash
php artisan schedule:work
php artisan queue:work --queue=default,reminders,scanning,mail
```

## Quality gates

```bash
php vendor/bin/phpunit          # or: php artisan test
php vendor/bin/pint --test      # formatting
php vendor/bin/phpstan analyse  # Larastan, level 1 (see PROGRESS.md for why not higher)
composer audit && npm audit --omit=dev
npm run build
```

## Where things are

| Area | Location |
|---|---|
| Business rules (deadlines, workflow, matching, reminders, importer, documents) | `app/Domain/*` |
| Public site, auth, student workspace | `app/Http/Controllers`, `app/Livewire`, `resources/views` |
| Staff administration | `app/Filament` (panel at `/admin`) |
| Permissions | `config/sas.php` (`permissions`), `app/Policies` |
| Schema | `database/migrations`; field notes in [docs/DATA_DICTIONARY.md](docs/DATA_DICTIONARY.md) |
| Requirement → code → test map | [docs/TRACEABILITY.md](docs/TRACEABILITY.md) |
| Deployment and operations | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), [docs/RUNBOOKS.md](docs/RUNBOOKS.md) |
