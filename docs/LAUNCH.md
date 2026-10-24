# Before the site goes public

A checklist for the day you move SAS from your computer to a real server. Nothing here has been done for you: hosting was deferred, and each step needs a decision or a login only you have. Tick them off in order.

## 1. Settings in `.env` on the server
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` set to the real address (https).
- A long random `APP_KEY` (`php artisan key:generate`). Never copy the local one.
- `SAS_TWO_STEP=true`, so staff must use an authenticator app. Staff accounts can read student documents and record payments.
- A real mail service (`MAIL_MAILER`, host, username, password, from address). With `log` or `array` no email leaves the server. The System health page warns about this.
- `SESSION_SECURE_COOKIE=true`. Set `SAS_TRUSTED_HOSTS` (comma-separated) if the site answers on more than the `APP_URL` host, for example a `www.` name. The site refuses requests that arrive under any other host name, which stops password-reset links being poisoned.
- `SAS_SLA_*` limits if 24 hours, 48 hours, 3 and 7 days are not what you want.
- `SAS_AGENT_INVITES_PER_DAY` (default 200): invitation emails one agency may send in a day.
- Never seed the demo accounts on the live server, and never copy `.env.example` to it unchanged: it is a development file. See `docs/SECURITY-REVIEW-2026-10-24.md` for what was fixed and what remains your decision (two-step sign-in, backup encryption).

## 2. Email that reaches inboxes
- On the domain's DNS add the mail provider's **SPF** and **DKIM** records (the provider shows them), and a **DMARC** record. Without them, invitations, receipts and reminders land in spam.
- Send a test to a Gmail and an Outlook address and check the spam folder.

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