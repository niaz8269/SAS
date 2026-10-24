# Portals, roles and test logins

Everything below was built and exercised in the browser on 2026-10-23. Test logins exist only on a development database (`php artisan db:seed --class=DemoAccountsSeeder`; refused in production). Password for all of them: `demo-password-123`.

Sign-in is by email and password only: **two-step sign-in (an authenticator-app code) is switched off** (owner's request, 2026-10-24). Setting `SAS_TWO_STEP=true` in `.env` brings it back: staff must then set up an authenticator app and students may opt in on a Security page. The demo staff accounts all share the secret `JBSWY3DPEHPK3PXP` (add it to any authenticator app) and the one-time recovery codes `demo-code-0001` to `demo-code-0008`.

| Portal | Address | Who | Demo login |
|---|---|---|---|
| Public website | `/` | everyone | none needed |
| Student workspace | `/student` | students | `demo-student@example.test` |
| Agent portal | `/partner` | people at a recruiting agency | `demo-agent@example.test` |
| Admin panel | `/admin` | all staff (what each sees depends on the role) | see below |

## The student journey
1. **Register and verify the email.** A newly verified student lands on the **Application form** and fills it in once (personal, contact, emergency contact, education, language, study plan, family, declaration).
2. **Choose scholarships** (`/student/choose-scholarships`): search by name or degree level (closed calls hidden unless asked) and press **Apply for this**. This creates the student's application (plan) for that scholarship.
3. **See the documents that scholarship needs.** The application page lists the mandatory documents first, then the other documents the scholarship mentions, each with its note and an upload button. Documents the scholarship does not ask for are in a collapsed "Other documents you can add" section. The list follows the admin's rules for that scholarship and the degree level the student picks (a scholarship with a single level needs no choice).
4. **Upload each document once.** An uploaded document counts for every scholarship that asks for that type, so a passport is not uploaded twice. Personal answers are never retyped.
When all mandatory documents are in, the student presses **Ask SAS to review my application**: SAS staff receive an enquiry (with a reference) and reply by email; nothing is charged. The Home page shows the three steps with one "Your next step" button and, per application, how many mandatory documents are provided. Everything else (documents vault, saved scholarships, notifications, profile, enquiries, security, privacy) is under "My account". When SAS opens a case for the same scholarship, the case uses the same degree level and document list.

## Student workspace
Dashboard, saved scholarships, application plans (private checklists), **cases and fees** (the applications SAS supports for the student, their status and what they owe), documents, profile, enquiries, notifications, two-step security and privacy (export or delete data).

Inside a case the student can read fees and how to pay, follow the plan checklist for the same scholarship (plans and cases link to each other), **message the counsellor** (the case shows "Reply needed" to staff until answered) and **share chosen documents** with the counsellor. Sharing needs a ticked consent, stores the consent text and time, can be withdrawn at any time, and only the assigned counsellor and case managers can open a shared document (route `/files/{document}/download`, authorised by `DocumentPolicy`, every download audited). Internal staff notes are never shown to the student. The dashboard summarises cases and fees owed.

**Application form and required documents.** The student fills one master **Application form** (`/student/application-form`, modelled on the CSC application form: personal, contact, emergency contact, education, employment, language, study plan, China history, contact in China, family, declaration). It is saved section by section, stored encrypted, prefilled from the account and profile, and included in the data export. Each case lets the student share the form with their counsellor (recorded consent, withdrawable); staff then see it under **Application form** on the case, and every viewing is audited.

Each case also shows a **Documents needed** checklist with an upload button per document. Admin controls it under **Service desk > Required documents** (permission `documents.manage`: administrators and service managers). Each document type has a default (mandatory, optional or not needed); **rules** override it for one scholarship, one degree level, or both, set either on the document ("Rules") or on a scholarship call ("SAS document rules"). The most specific rule wins: scholarship + degree level, then scholarship, then degree level, then the default. The student (or staff) sets the case's degree level. Staff see on the case only the documents the student shared. This list is separate from the public "Required documents" on a scholarship call, which comes from the official announcement. The starting list follows the CSC form's supporting-documents list; passport, photo, certificate and transcripts start as mandatory and everything else as optional, so review these defaults.

**Applying rules to any scholarship, now or later.** Three ways, all producing the same rules that staff can then edit: (1) on the scholarship call, **SAS document rules** > Add a rule; (2) **Copy rules from another scholarship** on the same tab (copied rules show "Not checked" until someone compares them with the new scholarship's own official pages); (3) a JSON file in `data/document-rules/<slug>.json` imported with `php artisan sas:import-document-rules` (format and document keys in `data/document-rules/README.md`; `--dry-run` checks first). Each rule can carry the official source URL and the date it was checked (shown to staff in the admin, not to students). The scholarship call list has a filter "No SAS document rules yet", and the admin dashboard counts scholarships that still use only the defaults. Any new call of an existing scholarship inherits its rules automatically, because rules belong to the scholarship, not to a year.

**CSC rules.** `php artisan db:seed --class=CscDocumentRulesSeeder` sets 17 rules for the Chinese Government Scholarship (CSC) from the 2026/2027 application notices of the Chinese Embassy in Indonesia and the Chinese Consulate General in Montreal (items a to m) plus the CSC form's supporting-documents list: mandatory for everyone are the photo, passport, highest diploma, transcripts, pre-admission letter, study plan (200 / 500 / 800 words), non-criminal record and the physical examination form; two recommendation letters are mandatory for master's and doctoral; the CSCA report is mandatory for bachelor's and hidden for other levels; HSK, English proof, portfolio and guardian documents are optional with a note saying when they are needed. Run it once on every server (it is safe to run again, but it resets these values). The same 17 rules are applied to the other CSC-based scholarships (Type A bilateral, Silk Road, Chinese University Program (Type B), Peking University), as the owner asked; their own university guides may differ (for example Type B needs a conditional offer first), so edit those scholarships' rules in the admin where they do.

Notifications: a case opened, counsellor assigned, status changed, payment recorded, invoice cancelled, refund paid and counsellor replies create an in-app notice and, if the student's "transactional" preferences allow, an email.

## Agent portal (`/partner`)
A partner account belongs to one **recruiting agent** organisation; every partner organisation is an agent (the earlier university and funder scholarship-submission area was removed). Staff create the agency and its people under **Recruiting agents** in the admin; there is no public sign-up, and the public page /partners explains how to get an account.

### What an agent does
An agent manages many students' applications. Staff mark the organisation as a **Recruiting agent** in the admin and create its people (`demo-agent@example.test` locally).
* **Backend home:** number of applications and students, applications by status, and what to do.
* **Student list:** search, **Add students** (surname, given name, email, country, passport number, date of birth, and a tick that the student agreed), **Batch import** (CSV, up to 200, with a template and a report of lines that were skipped), Edit, Open, **Add application**. Each student is emailed an invitation to choose **their own** password (valid 7 days); until then only the agent works on their applications. Every addition is audited with the agent's confirmation. An agent reaches only their own organisation's students; the student keeps their own account.
* **Application list:** application number, scholarship, student, gender, country, last operation time, status, review notes, application fee and agency charge status (from SAS's case, if any), **View details / Continue** and **Download application form** (a printable page). Search by student, email, scholarship or number; filter by status and dates.
* **Working on a student's application:** the same four steps as the student's (below), under `partner/students/{student}/...`: the details form, the documents and the submission belong to the student.
* **Account:** Edit organisation data and Change password (every partner user).

### The four-step application (student and agent)
1. **Apply now:** the scholarship, the degree and the language of study. 2. **Application form:** the master form on one page with a section menu and one Save. 3. **Upload materials:** a notice panel and a numbered slot per document the scholarship asks for (several files each); for a mandatory document the applicant may tick "Currently cannot provide this document" and give the reason, which counts as an excuse for now. 4. **Apply submit:** a summary (form, documents, reasons), a printable preview, and **Submit**, which is allowed only when nothing is missing.
Statuses: **Unfinished** (draft, or **Returned**) > **Submitted for review** > **Review passed**; after that SAS's own case status is shown. While an application is with SAS it is locked. Staff review under **Service desk > Applications to review**: they open the form and the documents the scholarship asks for (only while it is waiting for review), then **Return** with remarks (the applicant and agent are emailed and fix it) or **Pass**. A dashboard tile counts applications to review. The student's own list has All / Unfinished / Finished tabs and a status key.
## Admin panel roles
| Role | Demo login | Sees |
|---|---|---|
| Super administrator | `demo-super_admin@example.test` | everything, including users and roles |
| Administrator | `demo-administrator@example.test` | website, editorial, service desk, cases, finance, recruiting agents, payment options, users |
| Service manager | `demo-service_manager@example.test` | enquiries, consultations, **student cases** (opens cases, assigns counsellors), invoices (read), recruiting agents, services |
| Advisor (counsellor) | `demo-advisor@example.test` | only enquiries, consultations and **cases assigned to them** (changes the case status; reads their fees) |
| Writer or document specialist | `demo-specialist@example.test` | assigned enquiries and cases |
| Finance | `demo-finance@example.test` | **invoices and payments** only: records payments, cancels unpaid invoices, records refunds |
| Editor | `demo-editor@example.test` | scholarship programmes and calls (drafts), imports, articles, reference data |
| Reviewer | `demo-reviewer@example.test` | reviews and publishes what editors prepare |

## How fees work (see `/legal/refunds`)
Opening a case issues the **US$100 application fee**. If the funder **rejects** the application, **US$50** of a paid fee becomes a refund due (finance records when it is paid). If the student is **accepted**, the **US$500 agency service charge** is issued. Withdrawing before work starts refunds the whole fee; after work starts, nothing. Counsellors set the status; only finance and managers record payments. Students see the result under "Cases and fees". All amounts come from `config/sas.php` (`fees`).

