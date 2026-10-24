# Before the site goes public

A checklist for the day you move SAS from your computer to a real server. Nothing here has been done for you: hosting was deferred, and each step needs a decision or a login only you have. Tick them off in order.

## 0. The package and the content (prepared, ready to use)
- **Build the package** on your computer: `.\scripts\build-release.ps1 -Php C:\xampp\php\php.exe -Composer C:\Users\82335\Projects\tools\composer.phar`. It takes the last commit only (so no `.env`, no local database and no uploads can slip in), installs the packages without development tools, builds the CSS/JS and writes `dist\sas-release-<date>-<commit>.zip`.
- **Start the settings file** from `.env.production.example` (copy it to `.env` on the server and fill every `<...>`). The example is production-ready; `.env.example` is for development only.
- **Create the tables**: `php artisan migrate --force`. This also puts the starting reference rows in.
- **Load the content** (121 scholarships, 646 universities with the 27 medical ones, fees, guides, homepage, menus): `php artisan sas:import-content --replace`, then `php artisan sas:reindex-search`. The file is `data/launch/sas-content.jsonl.gz` (about 1.5 MB, 64,889 rows). It holds **no** users, students, cases, documents, payments or audit history, and the columns that name a staff account are blanked. `--replace` is refused once any student profile, case or document exists, so it cannot wipe a live site. Without `--replace` the command stops if the content tables already hold rows.
- **Then**: `php artisan sas:make-staff you@example.com "Your Name" super_admin`, `php artisan storage:link`, `php artisan optimize`, and add the cron line in step 3.
- To refresh the file after more editing on your computer: `php artisan sas:export-content`, commit it, and rebuild the package. On a live site do not re-import over real data; edit in the admin or use the importers (they never overwrite).
- Checked on 24 October 2026: all 490 pages in the sitemap return 200 (the 27 medical universities among them), the route/config/view/event caches build, the front-end build passes, and a copy of the content loaded into an empty database gives the same counts and a working search index.

## 1. Settings in `.env` on the server
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real address (https).
- A long random `APP_KEY` (`php artisan key:generate`). Never copy the local one.
- `SAS_TWO_STEP`: the owner has chosen **false** for now (staff sign in with email and password only). Staff accounts can read student documents and record payments, so turning it on (`true`, authenticator app) before real students register is strongly recommended; it needs no code change.
- A real mail service (`MAIL_MAILER`, host, username, password, from address). With `log` or `array` no email leaves the server. The System health page warns about this.
- `SESSION_SECURE_COOKIE=true`. Set `SAS_TRUSTED_HOSTS` (comma-separated) if the site answers on more than the `APP_URL` host, for example a `www.` name. The site refuses requests that arrive under any other host name, which stops password-reset links being poisoned.
- `SAS_SLA_*` limits if 24 hours, 48 hours, 3 and 7 days are not what you want.
- `SAS_AGENT_INVITES_PER_DAY` (default 200): invitation emails one agency may send in a day.
- Never seed the demo accounts on the live server, and never copy `.env.example` to it unchanged: it is a development file. See `docs/SECURITY-REVIEW-2026-10-24.md` for what was fixed and what remains your decision (two-step sign-in, backup encryption).

## 2. Email that reaches inboxes
- On the domain's DNS add the mail provider's **SPF** and **DKIM** records (the provider shows them), and a **DMARC** record. Without them, invitations, receipts and reminders land in spam.
- Send a test to a Gmail and an Outlook address and check the spam folder.
- **Sign-up depends on this email.** A new student registering, and an agent person added by staff (at their first sign-in), must type a 6-digit code emailed to them before they can use the site. The code works for 15 minutes, once, with 5 wrong tries, and a new one can be requested after a minute. With `MAIL_MAILER=log` (development) the code is only written to `storage/logs/laravel.log`. Test a real registration end to end before announcing the site.

## 3. The scheduler must run every minute
Reminders, the morning emails to counsellors and admins, the daily backup and the System health heartbeat all depend on it. On the server add one cron job (or the host's "scheduled task"):

`* * * * * php /path/to/site/artisan schedule:run >> /dev/null 2>&1`

Also keep a queue worker running if you switch the queue from `sync` to a real queue. After a minute, open **Administration > System health**: the Scheduler line must say "running".

## 4. Backups
- `php artisan sas:backup` (also daily at 02:30 by the scheduler) writes a zip of the database and the uploaded documents to `storage/app/backups` and keeps the newest 14.
- That zip is on the same disk as the site. **Copy it somewhere else** every day (another server, cloud storage or your own computer). Do a test restore once: unzip, put `database.sqlite` (or import the SQL) and the `documents/` folder back on a spare copy of the site and check you can sign in and open a document.

## 5. Accounts
- Create your own Super admin: `php artisan sas:make-staff you@example.com "Your Name" super_admin`. Never run the demo seeder on the live server (it refuses in production).
- Create Admin and Counsellor accounts only for people you trust; an Admin can see and change nearly everything, including money.
- Create each recruiting agency and its people under **Recruiting agents**.

## 6. Content you own
- Check the scholarship records and the document rules once more against the official pages (`data/document-rules`), the payment options under Website, the fees in `config/sas.php`, the privacy notice and the terms.
- Confirm the contact email, phone, WhatsApp number and address under Site settings.

## 7. After launch, every week
- Open **Needs attention** daily; open **System health** weekly; look at **Staff activity** (Administration) for anything unexpected.
- Keep PHP and the packages updated (`composer update`, then run the tests).