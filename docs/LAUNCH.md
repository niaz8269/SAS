# Before the site goes public

A checklist for the day you move SAS from your computer to a real server. Nothing here has been done for you: hosting was deferred, and each step needs a decision or a login only you have. Tick them off in order.

## 0. The package and the content (prepared, ready to use)
- **Build the package** on your computer: `.\scripts\build-release.ps1 -Php C:\xampp\php\php.exe -Composer C:\Users\82335\Projects\tools\composer.phar`. It takes the last commit only (so no `.env`, no local database and no uploads can slip in), installs the packages without development tools, builds the CSS/JS and writes `dist\sas-release-<date>-<commit>.zip`.
- **Start the settings file** from `.env.production.example` (copy it to `.env` on the server and fill every `<...>`). The example is production-ready; `.env.example` is for development only.
- **Create the tables**: `php artisan migrate --force`. This also puts the starting reference rows in.
- **Load the content** (121 scholarships, 646 universities with the 27 medical ones, fees, guides, homepage, menus): `php artisan sas:import-content --replace`, then `php artisan sas:reindex-search`. The file is `data/launch/sas-content.jsonl.gz` (about 1.5 MB, 64,948 rows, five blog posts included). It holds **no** users, students, cases, documents, payments or audit history, and the columns that name a staff account are blanked. `--replace` is refused once any student profile, case or document exists, so it cannot wipe a live site. Without `--replace` the command stops if the content tables already hold rows.
- **Then**: `php artisan sas:make-staff you@example.com "Your Name" super_admin`, `php artisan storage:link`, `php artisan optimize`, and add the cron line in step 3.
- To refresh the file after more editing on your computer: `php artisan sas:export-content`, commit it, and rebuild the package. On a live site do not re-import over real data; edit in the admin or use the importers (they never overwrite).
- **No dummy information is left.** On 25 October 2026 the demo scholarship, demo funder, demo agency, the demo cost lines and the 11 test logins were removed from the local database (php artisan sas:remove-demo-data --with-accounts), and every table and the content file were scanned for demo markers, example addresses and placeholder text: none found. The only login that remains locally is the owner account. The content file never carries accounts, students or stories anyway, so the live site starts with **no users and no testimonials**: create your Super admin (below) and the first stories will come from visitors through Share Your Story, after your approval.
- Checked on 25 October 2026 with APP_ENV=production, APP_DEBUG=false on an empty database (migrate --force, sas:import-content --replace, sas:reindex-search): all 502 sitemap pages plus robots.txt, the feed, the login pages and the story form answer correctly (an unknown address gives 404), no page shows demo or error text, and the route, view and event caches build. composer audit and 
pm audit report no known vulnerabilities.
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
- On **Administration > System health** press **Send a test email to me** (it tells you when mail only goes to the log), then send a real test to a Gmail and an Outlook address and check the spam folder. On a live site System health also looks up the SPF and DMARC records of the sending domain and shows a yellow line while one is missing. DKIM cannot be checked from outside: your mail provider shows "verified" on its own dashboard.
- Records to add in the domain's DNS settings (the provider gives the exact values): an **SPF** TXT record on the sending domain, for example `v=spf1 include:<provider's include> ~all`; the **DKIM** record(s) the provider shows; and a **DMARC** TXT record named `_dmarc`, for example `v=DMARC1; p=none; rua=mailto:info@your-domain` (start with `p=none`, move to `quarantine` after a few weeks of clean reports). Use one sending domain consistently in `MAIL_FROM_ADDRESS`.
- **Sign-up depends on this email.** A new student registering, and an agent person added by staff (at their first sign-in), must type a 6-digit code emailed to them before they can use the site. The code works for 15 minutes, once, with 5 wrong tries, and a new one can be requested after a minute. With `MAIL_MAILER=log` (development) the code is only written to `storage/logs/laravel.log`. Test a real registration end to end before announcing the site.

## 3. The scheduler must run every minute
Reminders, the morning emails to counsellors and admins, the daily backup and the System health heartbeat all depend on it. On the server add one cron job (or the host's "scheduled task"):

`* * * * * php /path/to/site/artisan schedule:run >> /dev/null 2>&1`

The same cron line also sends the queued emails once a minute (`SAS_QUEUE_VIA_CRON=true`, already in `.env.production.example`), so you do **not** need a separate always-running queue worker. Only on a server where you run your own worker (`deploy/supervisor-sas-worker.conf`) set `SAS_QUEUE_VIA_CRON=false`. Ready-to-edit files are in the `deploy/` folder: `crontab.txt` (the cron line), `supervisor-sas-worker.conf` (optional worker) and `nginx-sas.conf` (optional, for a VPS). After a minute, open **Administration > System health**: the Scheduler line must say "running", and "Background jobs (emails)" must say "being worked" (it turns red when emails wait more than 10 minutes).

## 4. Backups
- `php artisan sas:backup` (also daily at 02:30 by the scheduler) writes a zip of the database and the uploaded documents to `storage/app/backups` and keeps the newest 14.
- That zip is on the same disk as the site. **Copy it somewhere else** every day (another server, cloud storage or your own computer). Do a test restore once: unzip, put `database.sqlite` (or import the SQL) and the `documents/` folder back on a spare copy of the site and check you can sign in and open a document.

## 5. Accounts
- **Starter accounts:** `php artisan sas:create-starter-staff --super=you@your-domain --admin=admin@your-domain --counsellor=counsellor@your-domain` creates exactly one Super admin, one Admin and one Counsellor with long random passwords. The passwords are written to `storage/app/private/starter-staff.txt` (never shown in the terminal, never committed, not in the release package): sign in at `/admin`, open **Administration > Users** and change each name, email and password, then delete that file. People sign in with their email address (there is no separate username), so changing the email changes the sign-in name.
- **More staff:** the Super admin opens **Administration > Users > Add staff member**, enters a name, an email and Admin or Counsellor; the person gets an email with a link to choose their own password (set up the mail service first, section 2). Add as many Admins and Counsellors as you need, one account per person. On a person's page the Super admin can change the name, email and role, set a new password, send a reset link, or suspend the account; every change is in the audit trail. Only the Super admin can do these things; an Admin cannot. Another Super admin can only be added from the server: `php artisan sas:make-staff email "Name" super_admin`. Never run the demo seeder on the live server (it refuses in production).
- Create Admin and Counsellor accounts only for people you trust; an Admin can see and change nearly everything, including money.
- Create each recruiting agency and its people under **Recruiting agents**.

## 6. Content you own
- **Live chat (tawk.to):** the chat widget loads on the public pages only (never on sign-in, student, agent or admin pages, where a third-party script could read personal data). Set SAS_TAWK_EMBED to empty to turn it off. Sign in to tawk.to and add your live domain to the widget's allowed sites, set who answers and the offline message, and read the cookies and chat paragraph in the privacy notice (section 3 and 4) before approving the legal text: loading the widget sets tawk.to's own cookies, so check whether your visitors' countries need a cookie banner.
- **Legal texts:** read `docs/LEGAL-REVIEW.md` (a checklist of every fact the texts state), then press **Legal texts approved** on System health. Nothing else is needed in `.env`.
- **Blogs:** the five launch posts are marked "Not reviewed" in **Blog > Blog posts**. An Admin or the Super admin reads each one against its sources, then presses **Mark as reviewed** (the name and date are shown in the list); editing the words clears the mark so it always refers to what is on the page. Admins can add, edit, schedule, publish and delete posts there; Counsellors cannot. The same screen filters by Reviewed / Not reviewed.
- Check the scholarship records and the document rules once more against the official pages (`data/document-rules`), the payment options under Website, the fees in `config/sas.php`, the privacy notice and the terms.
- Confirm the contact email, phone, WhatsApp number and address under Site settings.

## 6b. Search engines (SEO)
- Set APP_URL to the one address you want in Google (for example https://sas.cas.moe, with or without www). Every page names that address as its canonical address, and obots.txt and the sitemap use it. Then make the server redirect the other forms to it with a permanent (301) redirect: http to https, www to the main name (or the reverse), and a trailing slash (/scholarships/) to the plain address. Apache does the trailing slash through public/.htaccess; on Nginx add ewrite ^/(.+)/$ /$1 permanent;.
- Open /robots.txt and /sitemap.xml on the live site and check both show your domain. Register the site in Google Search Console and Bing Webmaster Tools, prove ownership, and submit /sitemap.xml.
- The sitemap builds itself from the database: anything you publish (scholarship, university, destination guide, service, article, page) is in /sitemap.xml at once, and anything you unpublish or delete leaves it at once. Search engines then pick it up the next time they read the sitemap; nobody can make Google index a page on a given day, but a sitemap submitted in Search Console is re-read regularly. It lists about 500 pages now with the date each record last changed (the limit for one sitemap file is 50,000 addresses).
- Faster discovery (optional, later): Bing, Yandex and others accept an instant ping called IndexNow; Google ignores it. It is not built because it needs a key file and your decision to send addresses to those services. Scholarship addresses are like /scholarships/<name>/2026-intake; an address that was ever changed redirects to the new one automatically.
- Share preview: public/images/og-default.jpg (1200 by 630) is what WhatsApp, Facebook, LinkedIn and X show when a page has no picture of its own. Replace the file to change it.
- Search results show about 60 characters of a title and 155 of a description. Titles are cut automatically; write the descriptions of the About, Resources and Contact pages under Website, Other pages (they are only about 70 characters now).

## 7. After launch, every week
- Open **Needs attention** daily; open **System health** weekly; look at **Staff activity** (Administration) for anything unexpected.
- Keep PHP and the packages updated (`composer update`, then run the tests).