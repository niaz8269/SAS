# Portals, roles and test logins

Everything below was built and exercised in the browser on 2026-10-23. Test logins exist only on a development database (`php artisan db:seed --class=DemoAccountsSeeder`; refused in production). Password for all of them: `demo-password-123`.

Staff accounts must use an authenticator app. The demo staff accounts all share the secret `JBSWY3DPEHPK3PXP` (add it to any authenticator app) and the one-time recovery codes `demo-code-0001` to `demo-code-0008`.

| Portal | Address | Who | Demo login |
|---|---|---|---|
| Public website | `/` | everyone | none needed |
| Student workspace | `/student` | students | `demo-student@example.test` |
| Partner area | `/partner` | people at a partner university, funder or agent | `demo-partner_manager@example.test` |
| Admin panel | `/admin` | all staff (what each sees depends on the role) | see below |

## Student workspace
Dashboard, saved scholarships, application plans (private checklists), **cases and fees** (the applications SAS supports for the student, their status and what they owe), documents, profile, enquiries, notifications, two-step security and privacy (export or delete data).

Inside a case the student can read fees and how to pay, follow the plan checklist for the same scholarship (plans and cases link to each other), **message the counsellor** (the case shows "Reply needed" to staff until answered) and **share chosen documents** with the counsellor. Sharing needs a ticked consent, stores the consent text and time, can be withdrawn at any time, and only the assigned counsellor and case managers can open a shared document (route `/files/{document}/download`, authorised by `DocumentPolicy`, every download audited). Internal staff notes are never shown to the student. The dashboard summarises cases and fees owed.

Notifications: a case opened, counsellor assigned, status changed, payment recorded, invoice cancelled, refund paid and counsellor replies create an in-app notice and, if the student's "transactional" preferences allow, an email.

## Partner area
A partner account belongs to one organisation. Partners see an overview, submit scholarships (name, official page, level, dates and so on), follow each submission's status, read editors' messages, edit and resend while it is "Submitted" or "Needs changes", and keep their organisation's contact email and website. They never see student data. Staff create the organisation and its people under **Partners** in the admin; there is no public partner sign-up.

## Admin panel roles
| Role | Demo login | Sees |
|---|---|---|
| Super administrator | `demo-super_admin@example.test` | everything, including users and roles |
| Administrator | `demo-administrator@example.test` | website, editorial, service desk, cases, finance, partners, payment options, users |
| Service manager | `demo-service_manager@example.test` | enquiries, consultations, **student cases** (opens cases, assigns counsellors), invoices (read), partners and their submissions, services |
| Advisor (counsellor) | `demo-advisor@example.test` | only enquiries, consultations and **cases assigned to them** (changes the case status; reads their fees) |
| Writer or document specialist | `demo-specialist@example.test` | assigned enquiries and cases |
| Finance | `demo-finance@example.test` | **invoices and payments** only: records payments, cancels unpaid invoices, records refunds |
| Editor | `demo-editor@example.test` | scholarship programmes and calls (drafts), imports, articles, reference data |
| Reviewer | `demo-reviewer@example.test` | reviews and publishes what editors prepare |

## How fees work (see `/legal/refunds`)
Opening a case issues the **US$100 application fee**. If the funder **rejects** the application, **US$50** of a paid fee becomes a refund due (finance records when it is paid). If the student is **accepted**, the **US$500 agency service charge** is issued. Withdrawing before work starts refunds the whole fee; after work starts, nothing. Counsellors set the status; only finance and managers record payments. Students see the result under "Cases and fees". All amounts come from `config/sas.php` (`fees`).

## How a partner submission becomes a listing
Partner submits -> editors mark it "Being checked", ask for changes (the partner sees the message and resends) or reject it -> "Accept and create the draft listing" makes a **draft** in Scholarship calls through the normal importer. A draft is never public: its official source is checked and a second person reviews it before publication.