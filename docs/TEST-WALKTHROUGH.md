# Walkthrough: test every role yourself

Start the site on your computer. `php artisan serve` crashes on this Windows setup, so use this from the project's `public` folder (XAMPP's PHP):

```
C:\xampp\php\php.exe -d opcache.enable=0 -d opcache.jit=0 -S 127.0.0.1:8000 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Then open `http://127.0.0.1:8000` and tick each line. Use a private (incognito) window per role so the sign-ins do not mix. Write what is wrong next to the line, or send it to me as it is.

**Demo accounts** (password `demo-password-123`; local test data only, never create these on the live server)

| Role | Sign in at | Email |
|---|---|---|
| Student | `/login` | `demo-student@example.test` |
| Agent | `/login` | `demo-agent@example.test` |
| Counsellor | `/admin` | `demo-advisor@example.test` |
| Admin | `/admin` | `demo-administrator@example.test` |
| Super admin | `/admin` | `demo-super_admin@example.test` |

Two-step sign-in is switched off, so no authenticator code is asked.

## 1. Public site (no sign-in)
- [ ] Home: figures strip, search with nationality, scholarships, universities, destinations. Nothing says "will appear here".
- [ ] Scholarships: search by nationality (pick your own), degree, subject, funding. A scholarship you cannot apply for does not appear.
- [ ] Open three scholarships: eligibility, funding table, required documents, sources and "last verified" all filled in; deadlines never invent a time.
- [ ] Universities: open Xuzhou, Capital and Nanjing Medical. Fees add up, sources are linked, scholarships are listed. Open one that is hidden (for example Harbin Medical): it must say not found.
- [ ] Destinations: open five countries; the guides read well and the official links open.
- [ ] Phone size (resize the browser to 375 px): no sideways scrolling, buttons large enough.
- [ ] Contact and consultation forms send; you get a confirmation.

## 2. Student (`demo-student`)
- [ ] Dashboard shows next steps. "Choose scholarships" recommends scholarships and lets you tick several.
- [ ] Application form: red star only on required fields; at least two education records; all dates are whole dates.
- [ ] Download the filled application form (PDF) and check that the answers sit in the right boxes.
- [ ] Documents: upload a PDF and a photo, see the checks, the blank-form download buttons.
- [ ] An application's page: stages, journey tasks, required documents, submit.
- [ ] My counsellor: write a message; a counsellor reply appears in the same conversation (not on the case page).
- [ ] Book a call: you see only your own counsellor's free times.
- [ ] A case page: fees and receipts download, no sharing boxes, "who can see your details" is correct.
- [ ] Privacy: download your data (it includes the conversation, goals and calls); "Your agency" appears only for students an agent added.

## 3. Agent (`demo-agent`)
- [ ] Students list, add one student, import a CSV (template download), invitation sent, daily invitation limit shown if reached.
- [ ] Open a student: application form, documents, apply to several scholarships at once.
- [ ] You cannot change the email of a student who has chosen their own password.
- [ ] Messages with SAS about a student; statement of fees by month and its CSV.
- [ ] You cannot see another agency's students (try changing the number in the address).

## 4. Counsellor (`demo-advisor`)
- [ ] Students: only your assigned students; open one: overview, form, applications, all documents, comment on a document.
- [ ] Add a status update with the contact type and next step; the student's "last update" changes.
- [ ] Reply in Messages (with a saved reply template); the student is notified.
- [ ] My calendar and My availability: add free times, see a booking.
- [ ] Counsellor report and daily digest email content.
- [ ] Cases: you see your own cases only; the case form lists only the case's student.

## 5. Admin (`demo-administrator`)
- [ ] Needs attention: unassigned students, replies owed, applications waiting for review, overdue fees.
- [ ] Assign students (also "assign to the least busy" in bulk), counsellor workload, agent overview.
- [ ] Enquiries: convert one into a student; announcements; invoices and receipts (PDF).
- [ ] Imports: download each Excel template, upload a small file for scholarships, universities and programmes, read the preview, commit. Nothing goes public by itself.
- [ ] Reference data: Medical fees and University scholarships (show/hide bulk actions), Countries (show the study guide publicly), Universities.
- [ ] Scholarship Cycles: open a draft, read the sources, submit for review, approve (an administrator needs a reason to approve their own change).
- [ ] Homepage and Site settings: change a heading and see it live.

## 6. Super admin (`demo-super_admin`)
- [ ] Users and roles: invite staff, change a role; only the super admin can.
- [ ] Staff activity (audit): filter by person, topic, student, dates; no student document text appears.
- [ ] System health and backups: run a backup; the health page warns if mail is not real or two-step is off.
- [ ] Reports and CSV exports (formula-safe).

## 7. Things only you can decide (open items)
- [ ] Publish the 66 draft scholarships you approve (`docs/INTERNATIONAL-DATA-2026-10-24.md`).
- [ ] Show the 25 hidden country guides you approve (Countries, bulk action).
- [ ] Check the hidden medical universities and the judgement calls (`docs/MEDICAL-UNIVERSITIES-2026-10-24.md`).
- [ ] Decide about two-step sign-in, payments, SMS/WhatsApp and translation (`docs/SECURITY-REVIEW-2026-10-24.md`, `docs/LAUNCH.md`).
