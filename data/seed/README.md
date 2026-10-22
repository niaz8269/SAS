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
## china-priority-2026-10-batch4.csv (checked 2026-10-22)

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| Shanghai Jiao Tong University international graduate scholarships | SJTU 2026 graduate admissions guide (university) | Scholarships by level, channels (Type B agency 10248 / Type A), age limits, fee, round deadlines | No stipend amounts on the page. |
| Zhejiang University doctoral scholarship routes | ZJU 2026 doctoral guide (university) | All routes with per-route deadlines, eligibility, fee | No amounts or language thresholds on the page read. |
| Marine Scholarship of China | Tongji University notice, 2026 intake (university) | Countries, levels, fields, RMB 3,000/3,500 allowance, agency 10247, window 25 Oct to 20 Dec 2025 | One host university; no next call found. |

Fudan's international office page returned only notice titles, so Fudan was not seeded.
### Still to do for China
CAS-TWAS President's Fellowship (official page found only for the 2017 call), Fudan (2026 CGS / Silk Road / Shanghai Government windows seen only in search snippets), Jiangsu Jasmine (no current official notice), other Type B / Silk Road / Bilateral universities, and the CSC portal itself (blocks automated access; its facts come from consulate and university pages).

## global-2026-10-batch1.csv (checked 2026-10-22) - first non-China batch

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| Chevening Scholarships (UK), 2027-28 | chevening.org timeline, eligibility and coverage pages (government) | Full eligibility rules, itemised coverage, cycle timeline, offer deadline 8 July 2027 17:00 BST | Closing day given only as "October 2026, 11:00 UTC; see your country page", so the cycle may already be closed. |
| Erasmus Mundus Joint Masters | Erasmus+ page (European Commission) | Who can apply, how scholarships work, usual Oct-Jan window | No amounts; each master sets its own deadline and host countries (no destination recorded). |
| Turkiye Burslari | turkiyeburslari.gov.tr overview (government) | Levels and support categories | No eligibility, amounts or dates on the pages read; the calendar page was not available. |

Not yet researched: Fulbright (country-specific), DAAD (hundreds of programmes), Australia Awards (the DFAT page timed out), Korean GKS (page returned 404), Gates Cambridge, Commonwealth, and everything else.
## global-2026-10-batch2.csv (checked 2026-10-22)

| Programme | Source read | What it supports | Gaps |
|---|---|---|---|
| Commonwealth Master's Scholarships (UK), 2027-28 | cscuk.fcdo.gov.uk Master's page (government) | Itemised coverage and stipend rates, 43 eligible countries, eligibility, nominator route | Closing time given (16:00 BST on a Tuesday 20 October) but no year on the page; 2026 is inferred. Eligible countries are listed in the text, not as structured nationality criteria, so matching reports them as unconfirmed. |
| Global Korea Scholarship (GKS) | studyinkorea.go.kr GKS tab (government) | Tracks, parent-nationality rule, age and GPA limits, coverage items | No allowance amounts or exact dates; application was not open when checked. |

Tried but not seeded: Gates Cambridge (the pages returned only a short description, no eligibility or dates), Australia Awards (the DFAT pages timed out), Fulbright and DAAD (not attempted: country-specific and database-style, needing a different approach).