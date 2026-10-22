# Researched seed data

Files here are **drafts for editorial review**, not published listings. Import with:

```bash
php artisan sas:import-scholarships data/seed/china-priority-2026-10.csv           # validate only
php artisan sas:import-scholarships data/seed/china-priority-2026-10.csv --commit  # create DRAFT cycles
```

Rows become drafts without a reviewer, `fully_funded_verified` stays off, and nothing is published. A reviewer must open each cycle, re-check the official source, add or correct details, and approve it (a different person from whoever submitted it).

## china-priority-2026-10.csv (checked 2026-10-22)

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| Chinese Government Scholarship (CSC) | PRC Consulate General in Montreal, 2026-27 call (government) | Application via campuschina.org; age/HSK limits for that channel; Canada deadline 22 Feb 2026 | The CSC portal itself and csc.edu.cn returned errors to the research tool, so the benefits, current categories and the 2027 call are **unverified**. Deadline recorded as channel-dependent. |
| Beijing Government Scholarship | english.beijing.gov.cn notice dated 23 Jan 2026 (government) | Categories A/B/C, age, language and GPA rules, application via host institution | No stipend amounts; deadlines set by each institution. |
| Shanghai Government Scholarship | Shanghai Maritime University guide, 2026 intake (university) | Type A/B coverage, stipend amounts, age limits, 1 Dec 2025 to 30 Apr 2026 window at that university | Municipal commission page not found; other universities' windows differ; the Fudan notice was a PDF the tool could not read. |
| Schwarzman Scholars, Class of 2028-29 | schwarzmanscholars.org/admissions (funder) | Age, degree, English test minimums, application contents, application periods | Page does not state coverage; no exact dates yet. |
| Yenching Academy Scholarship, Sept 2027 intake | yenchingacademy.pku.edu.cn (university) | Deadline 30 Nov 2026, "full fellowships", contact | Page gives no time/zone, eligibility detail or itemised coverage. |

## Looked for but not seeded

- **Confucius China / CLEC scholarship**: the official site (cis.chinese.cn) was not reachable; only older university notices were found.
- **Jiangsu Jasmine, Zhejiang Government Scholarship**: no current provincial notice found (only older or single-university pages and third-party sites).
- Individual Chinese university scholarships, and every other country: not researched yet.

Wording is the project's own paraphrase of facts; no source text was copied. Third-party aggregator sites were deliberately not used as evidence.
