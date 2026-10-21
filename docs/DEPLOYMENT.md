# Deployment

Nothing here has been executed against a real server. The application has only been run locally (SQLite, `database` queue, local disks). Treat this as a checklist to validate on staging. Do not deploy to production without the owner's explicit authorisation.

## Topology (OPS-01/02)

- One Linux host (2 to 4 vCPU, 4 to 8 GB to start; validate under load): Nginx + PHP-FPM 8.4, a supervised queue worker, and the scheduler.
- Managed MySQL 8.4, Redis, two private S3-compatible buckets (public media, private documents), ClamAV (clamd on a private port) or a managed scanner.
- Shared hosting is unsuitable: it cannot run supervised workers or the scheduler reliably.

## First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env   # then fill from the secret store; never commit .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force          # TaxonomySeeder + draft service catalogue only (no demo data)
php artisan storage:link             # public_media only
php artisan filament:upgrade
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan sas:make-staff <owner-email> "<Name>" super_admin
```

Required production environment values:

| Variable | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | the https URL |
| `DB_CONNECTION` | `mysql` (+ host, database, user, password) |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `redis` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SAS_DOCUMENT_DISK_DRIVER`, `SAS_DOCUMENT_BUCKET` | `s3` / private bucket (no public ACL, server-side encryption on) |
| `SAS_MEDIA_DISK_DRIVER`, `SAS_MEDIA_BUCKET`, `SAS_MEDIA_URL` | separate public bucket/CDN |
| `SAS_SCANNER` | `clamav` (`fake` is refused in production) |
| `CLAMAV_HOST`, `CLAMAV_PORT` | private address of clamd |
| `MAIL_*` | transactional provider; configure SPF, DKIM, DMARC on the sending domain |
| `TRUSTED_PROXIES` | load balancer addresses |
| `SAS_LEGAL_ENTITY`, `SAS_CONTACT_EMAIL`, `SAS_ACCESSIBILITY_EMAIL` | owner-confirmed |

Production must not contain the `DemoSeeder` accounts. `DemoSeeder` refuses to run when `APP_ENV=production`; verify with `select email from users where email like '%@example.test'` after deploy.

## Processes

Supervisor (or systemd) program, restarted on every release with `php artisan queue:restart`:

```
php artisan queue:work redis --queue=default,mail,reminders,scanning --tries=5 --backoff=60 --max-time=3600
```

Cron: `* * * * * php /srv/sas/artisan schedule:run` (the schedule runs deadline evaluation every minute, reminder dispatch every 15 minutes, retention cleanup daily; all use locks so several app instances do not duplicate work; `onOneServer` requires a shared cache such as Redis).

Laravel Horizon is the recommended supervisor for Redis queues (`composer require laravel/horizon`, then `horizon:install`). It is **not installed** in this repository because there was no Redis available to verify it; add it on staging and set separate queue priorities `mail`, `reminders`, `scanning`.

## Release procedure (OPS-03)

reviewed change → CI green → staging deploy → `php artisan migrate --force` → owner UAT → production deploy → health check `GET /up` → `queue:restart`.
Migrations must be backwards compatible. To roll back code, redeploy the previous release; do not assume destructive migrations can be reversed.

## Headers and CSP

`SecurityHeaders` sets nosniff, frame, referrer and permissions policies, HSTS in production, and `no-store` + `noindex` on private areas. A Content-Security-Policy is **not** set: Alpine/Livewire need either `unsafe-eval` or the CSP build plus nonces. Plan this on staging and enable it in report-only mode first.

## Backups and recovery (OPS-05)

Target RPO 24 h, RTO 8 h. Daily encrypted database backups kept 30 days with separate credentials; object versioning on both buckets. A restore drill has **not** been performed: do one before launch and quarterly (see RUNBOOKS.md).
