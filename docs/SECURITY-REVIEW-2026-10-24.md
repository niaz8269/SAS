# Security review, 24 October 2026

A read-only review of the whole application (authorisation, documents, injection and output, sign-in, redirects, webhooks, mass assignment, privacy) found **no critical issue**, 1 high, 7 medium and 12 low. Document, zip, invoice and PDF access, agent-to-agent isolation, Filament scoping and bulk actions, raw SQL, the HTML cleaner and CSV formula protection were checked and held. Everything below marked "fixed" has a test in `tests/Feature/Admin/SecurityHardeningTest.php`.

## Fixed

| Finding | What was done |
|---|---|
| **High.** An agent could change the email of a student who had taken over their account, then send an invitation to the new address and take the account | Changing the email of, or inviting, a student whose email is verified is refused; only the student can. Agencies are also limited to 200 invitations a day (`SAS_AGENT_INVITES_PER_DAY`) |
| Stored script injection through the structured-data block on scholarship pages | The block is encoded safely. This also revealed that the block had been broken since the framework upgrade (its `@context` key was read as a template command); it is valid again |
| Password-reset links could be poisoned through a forged Host header | The site answers only its own host names (`APP_URL`, plus `SAS_TRUSTED_HOSTS` if needed) |
| A counsellor could read every student's name and email in the case form | The case form lists only the case's own student; a new case finds students by typing 2 or more letters |
| Agents kept access to a student's documents for ever | Students can end their agency's access on the Privacy page (password required, audited) |
| Typed text became clickable links in emails sent from the SAS address | Names and messages are made plain text in all five affected emails |
| The staff panel sent no clickjacking, content-type or referrer headers | Added to the panel |
| An ordinary password-reset link was valid for seven days | Agent invitations now use their own table; ordinary links last one hour again |
| A student could book another counsellor's call time | A student with a counsellor books only that counsellor's times |
| Analytics rows written on every public view without a limit | Scholarship pages and outbound links are rate limited |
| `/\host` accepted as a local link by the HTML cleaner | Refused |
| Erasing an account left addresses and titles in the audit log | Cleared for the person's own entries and for entries about their documents, forms and applications |
| A tiny image file could declare a huge canvas and exhaust the server when turned into a PDF | Images over 40 megapixels are refused |
| No limit on guessing one account's password from many addresses | An extra per-account limit (30 an hour) |
| Demo accounts (published password) could be seeded on any non-production server | Only on a local address (localhost, `.test`) |

## Not changed, and why

- **Two-step sign-in is off by default** (`SAS_TWO_STEP=false`). That is your decision (no authenticator code at login), but it means a staff account is protected by its password alone. Before real students' passports are held, either switch it on or use long unique passwords and keep the number of super admins small.
- **Registration and agent forms say when an email already has an account.** Hiding it would make sign-up confusing; the risk is low (limited to 10 an hour per address).
- **Backups are plain zip files** (database and, on the local disk, documents). Store them only on encrypted storage and copy them off the server; encryption with a key from the environment is the next step.
- **`.env.example` is a development file** (`APP_ENV=local`, `APP_DEBUG=true`, mail to the log). The production values are in `docs/LAUNCH.md`; never copy `.env.example` to a live server unchanged.
- **Partner logins created by staff use a typed password** (12 characters or more). Prefer a random password and a reset link when you create them.
- **A submitted application can still be edited through the master form or by deleting a document.** Locking these changes how agents and students correct a returned application, so it needs a decision before it is built.
