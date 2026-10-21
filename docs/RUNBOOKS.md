# Operational runbooks

## Failed or stuck queue jobs

1. Admin dashboard shows *Failed jobs* and *Failed reminders* (reports.view).
2. `php artisan queue:failed` to list; `php artisan queue:retry <id|all>` after fixing the cause. Jobs are safe to replay:
   - `SendReminderEvent` rechecks consent, publication, deadline version and per-channel delivery before sending.
   - `ScanDocumentJob` only acts on `pending` files; the scanner being down leaves the file quarantined.
3. `php artisan queue:prune-failed --hours=720` runs daily from the scheduler.

## Reminders did not go out

- `select status, count(*) from notification_events group by status;`
- `pending` with `send_at` in the past: the scheduler or worker is not running (`schedule:run` cron, `queue:work`).
- `skipped` reasons: `no_consent`, `account_inactive`, `cycle_not_published`, `deadline_passed`. `cancelled`: `deadline_changed`, `no_longer_tracked`.
- Rolling, not-announced and route-dependent deadlines intentionally never create reminders.

## Document scan backlog / malware scanner down

- Students see *Pending scan*; nothing is downloadable until `scan_status=available`.
- Check clamd: `echo PING | nc $CLAMAV_HOST $CLAMAV_PORT` should answer `PONG`. Restart clamd; jobs retry with backoff (30 s … 1 h, 8 tries). After the final try the file stays pending and the failure is reported: re-dispatch with `ScanDocumentJob::dispatch($versionId)`.
- Never mark a file available by hand.

## Correction reports and "Needs recheck"

- A correction report sets the cycle's `review_due_at` to now, so it shows "Needs recheck" and drops out of promoted placements until a reviewer re-verifies it. Work the *Correction reports* list; open the cycle, check the official source, update, and resolve the report. Verified cycles get a new `review_due_at` (30 days, 7 days when closing within 30) on publish.

## Changing a deadline (extension, correction)

Admin → Scholarship cycles → cycle → *Deadlines* → *Set or change deadline*. Record the reason. The old row is superseded (history kept), pending reminders for the old version are cancelled, and replacements are scheduled. Never edit deadlines in the database directly.

## Importing scholarships

Admin → Imports → download the template → upload. Fix every red row (the error file can be downloaded), then *Commit*. Imports only create or update **drafts**; each cycle still needs source evidence, review and publication. Re-uploading the same file is safe (matched by `external_key`, or programme + year).

## Suspending a user

Admin → Users → edit → status *Suspended*. All sessions are deleted immediately; the next request is refused. Only super administrators can change roles; role changes and CLI grants are audited.

## Moderation of content

Rich text is sanitised on output (`App\Support\Html`). Editors cannot publish; reviewers publish after a second person approves.

## Restore drill (to be performed before launch)

1. Restore the latest DB backup to an isolated instance; run `php artisan migrate --force`.
2. Confirm row counts, log in, open a published scholarship and a student document download.
3. Replay deletions (PRIV-04): `php artisan sas:replay-deletions`. The `deletion_tombstones` table (keyed email hashes only) must survive the restore; if the restore replaced it with an older copy, re-apply the tombstones kept from the live system first. Confirm deleted users and their documents are gone before reopening access.
4. Record time taken against RTO 8 h.

## Incident basics

Disable the affected feature, preserve logs, rotate credentials held in the secret store, and tell the owner. Never log passport numbers, CV text, tokens or bank statements.
