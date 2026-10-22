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

## china-priority-2026-10-batch2.csv (checked 2026-10-22)

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| CAS-ANSO Scholarship (Degree Program), 2026 call | anso.org.cn call page (funder) | Eligibility (born on/after 1 Jan 1996 master's, 1991 PhD), how to apply via UCAS/USTC, deadline 31 Jan 2026 Beijing time | Coverage list is an image the tool could not read, so amounts are unknown. No 2027 call listed yet. |
| Silk Road Program (Chinese Government Scholarship) | HUST 2026 notice (university) | Countries, HSK/CSCA rules, CNY 2,500 monthly stipend, fee, deadline 20 May 2026 | One university only; other universities differ. No time zone stated. Recorded as a single undergraduate example. |
| CGS University Program (Type B) | SUFE 2026 notice (university) | Stipends, agency number, three-stage timeline, deadline 28 Feb 2026 | One university only. |
| Peking University scholarships for international graduate students (CSC, PKUS, BGS) | isd.pku.edu.cn notice, 12 Jan 2026 (university) | Three scholarships from one application; exact deadlines 9 Mar 09:00 and 17 Mar 24:00 Beijing time | Notice does not print the year beside the dates (dated January 2026, so 2026 assumed); no cash amounts. |
| UNESCO/PRC Great Wall Co-Sponsored Fellowships, 2026-27 | unesco.org/en/fellowships/greatwall (funder) | 75 fellowships (27 new), age limits, route via National Commissions, deadline 23 Jan 2026 | Benefits not listed; the page was updated after the deadlines yet still says open. |

## china-priority-2026-10-batch3.csv (checked 2026-10-22)

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| International Chinese Language Teachers Scholarship (CLEC) | SYSU 2026 guide, MTCSOL master's (university) | Age, HSK/HSKK, recommendation rules, RMB 3,000 monthly allowance, deadline 15 May 2026 Beijing time | CLEC's own site was unreachable; one university's master's route only. |
| CGS Bilateral Program (Type A) | SYSU 2026 guide (university) | Eligibility, documents, two-portal process, CSCA for bachelor's | Rolling channel with no final date on the page; amounts follow the CSC's annual announcement. |
| Tsinghua graduate admissions and scholarships (CGS, BGS) | yz.tsinghua.edu.cn 2027-entry notice (university) | Who can apply, September-February window, RMB 800 fee, CGS/BGS coverage | No Tsinghua-specific scholarships described; deadlines set per programme. |
### Still to do for China
CAS-TWAS President's Fellowship (official page found only for the 2017 call), Jiangsu Jasmine and Zhejiang provincial awards (no current official notice), the other Type B / Silk Road / Bilateral universities, Tsinghua-specific and other individual university awards, and the CSC portal itself (campuschina.org and studyinchina.csc.edu.cn block automated access; their facts are taken from consulate and university pages).
