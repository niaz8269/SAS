# Security check, 12 November 2026 (after a hacked site on the owner's other hosting)

Scope: the live site `https://sas.cas.moe` (plain GET/HEAD/OPTIONS requests only, nothing was changed or attacked), the files visible in the 20i File Manager, the code paths that accept files, and the locked dependencies. It does **not** replace a review by a security professional, and it cannot show that the server was never touched: the host's own logs and malware scan are the evidence for that.

## Found fine
- No `.env`, `.git`, log, database, backup, helper-script or `phpinfo` file is reachable (403/404). The 404 page and an odd address leak no details.
- HTTPS is forced, HSTS is on (1 year), the session cookie is `secure` and `httponly` with `samesite=lax`, TRACE is refused, PUT/DELETE give 405.
- `/admin`, `/student` and `/partner` redirect to the login page; the admin login is the only public staff address.
- The live web folder (`sas-app/public`) holds only `index.php` (543 bytes, the standard one), `.htaccess`, `favicon.ico` and the folders build, css, fonts, images, js and storage; every date is from the owner's own releases (25 and 27 October). The public-upload folder behind `/storage` is empty. 20i's File Manager scanner reports "No malware found".
- Student document upload: allow-list of 4 types, double-extension block, real file-signature check, random storage names, private disk outside the web folder, quarantine until scanned. The public story photo is decoded and re-saved as a new JPEG, so anything hidden in the file is removed. No `eval`, `unserialize` or shell call uses user input.
- JavaScript packages: 0 known vulnerabilities (`npm audit`).

## Found and fixed in the code (in this release)
| Finding | Fix |
|---|---|
| Folders were listed (`/images/`, `/build/`, `/fonts/`, `/storage/`): the `-Indexes` setting sat inside a block for a module this host does not load | `.htaccess` now refuses any folder address (403) |
| Any `.php` file placed in the web folder (the classic "planted shell") would have run | `.htaccess` now refuses every `.php`, `.phtml` and `.phar` address except `index.php`, also under `/storage` |
| No Content-Security-Policy; PHP version announced in `X-Powered-By` | Baseline policy (`frame-ancestors 'self'; base-uri 'self'; object-src 'none'`), `Cross-Origin-Opener-Policy`, and the version header removed |
Tests: `SecurityHeadersTest`.

## Update, 13 November 2026
Item 1 below is fixed in the code and on the server (12 packages moved by patch versions; `composer audit` reports no advisories). Items 2 to 7 still need the owner.

## Found, needs the owner's decision or action
1. **(Fixed 13 November) Five Symfony packages were a few patch versions behind published security fixes** (`html-sanitizer` 8.1.8, `http-foundation` 8.1.9, `http-kernel` 8.1.9, `routing` 8.1.9 need 8.1.11; `mime` 8.1.7 needs 8.1.10). The fixes concern HTML-sanitiser allow-lists, host and proxy handling and email-header injection. None is reached directly by the site's own code (it does not call the sanitiser; hosts are pinned by `trustHosts`), so the risk is low, but they should be updated. This needs Composer (not installed on the working machine) and the five folders under `vendor/symfony/` uploaded to the server.
2. **Two-step sign-in is off for staff** (the owner's earlier choice). A staff password alone protects student passports and the whole database. Turning it on (`SAS_TWO_STEP=true`) is the single most useful protection if a password leaks.
3. **Starter and dummy staff accounts** (`owner@sas.test`, `admin@sas.test`, `counsellor@sas.test`) and the file `storage/app/private/starter-staff.txt` on the server hold starter passwords. Replace the accounts with real ones, set long unique passwords, and delete that file. This is the most likely way in for anyone who has seen the account list.
4. **The same hosting package serves another site** (`public_html`, the "CAS-SAAS" repository): every site in one 20i package runs as the same Linux user (3286505), so a compromised neighbour can read `sas-app/.env` and the database. If the hacked site is in this package, treat this one as exposed: change every password and the `APP_KEY`/database credentials, and review the neighbour first. Giving SAS its own package removes that risk.
5. **ClamAV is not available** on this host, so uploaded documents stay quarantined for manual review (safe, but not automatic). Staff must open documents only from a machine with antivirus.
6. **Backups** are plain zip files: keep copies off the server, on encrypted storage.
7. **Helper scripts on the server** (`sync-from-repo.php` in `sas-app`, outside the web folder) are not reachable from the web, but remove them when the owner no longer needs them.

## If the other site's hack came through an uploaded file
Typical signs: a `.php` file in an uploads or cache folder, a changed `index.php` or `.htaccess`, new admin users, unfamiliar scheduled tasks. On this site: Scheduled Tasks should show only the `schedule:run` cron entry; Users in the admin should show only known people; `index.php` should be 543 bytes.
