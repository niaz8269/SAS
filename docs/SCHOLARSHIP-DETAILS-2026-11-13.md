# Complete scholarship details, 13 November 2026

The owner asked that every scholarship carry the complete information a student needs, as the official source states it, so that nobody has to
open the official site to learn how it works. This was done for **113 of the 117 published scholarships**, from official sources only (the funder,
the government or ministry, the awarding university, or an embassy notice for that scholarship).

## What each scholarship now has

- **Application process** in order: how to apply, how applicants are selected, and what happens after selection (new section on the page, a "How do I apply?" quick answer with matching FAQ markup, and an editor in the Admin: scholarship, *Application process*).
- **Funding components**, each with its amount, period and conditions, including what is *not* covered.
- **Required documents** in the source's order, with format rules and conditions.
- **Complete eligibility** (nationality, education, results, age, experience, language, exclusions) and a fresh summary.
- **Programmes per degree level**: the programmes the source names (up to 25 per level, with the catalogue address where there are more) and a summary line per level (duration, language, entry rule).
- The **sources read** and the date they were checked.

Before and after (published scholarships): application steps 0 to 113 of 116; scholarships with named programmes 19 to 64; named programme lines now 1,579; average required documents 4.6 to 8.6; average funding components 3.9 to 6.1.

## How it was done and its rules

Researched page by page in 22 batches. Each file follows sas:import-details (in pp/Console/Commands): it replaces only the sections it contains, keeps a longer stored programme list, never publishes or unpublishes, and never marks funding as verified, so nothing is called "fully funded" that was not. Text is paraphrased, carries no web addresses, and says nothing the page did not say: what a page does not state is left out. Files are in database/data/scholarship-details/2026-11-13 and are applied by the migration 2026_12_12_000002_load_scholarship_details_wave_1. Re-running it changes nothing.

Four scholarships were **not** changed because their official sites block automated reading (a browser check or HTTP 403): MBZUAI, University of Melbourne research scholarship, Reach Oxford, and the Israel MFA scholarship (removed, see below). A person with a normal browser needs to read their official pages; the data can then be filled the same way.

The **Israel Ministry of Foreign Affairs scholarship** was set back to draft at the owner's request (migration 2026_12_12_000003). It is kept, not deleted.

## Stored facts that the official pages now contradict (editors to review)

The importer does not change closing dates or the funding label, so these need an editor's decision:

## Needs a person with a normal browser / not enriched
- ae-mbzuai-graduate-funding-2027: mbzuai.ac.ae behind Cloudflare browser check; no file written. Stored CGPA 3.0/3.5 vs policy PDF 3.2; stored 2026-27 dates and stipend unconfirmed.
- au-australia-awards-2027, ro-mfa-government-scholarships-2026: pilot output below bar (no steps/docs; AU levels from snippets). Redo in a later wave.
## Stored dates/facts contradicted by newer official pages (importer does NOT change deadlines/milestones)
- sa-kfupm-graduate-scholarship-2027: college site now 1 Nov 2026 - 15 Dec 2026 for thesis MS and PhD; stored 23 Nov 2026 - 20 Jan 2027.
- jp-mext-embassy-undergraduate-2027: stored intake text "April 2028 arrival, recruitment in 2027" vs guidelines: arrival 1-7 April 2027.
- sa-moe: stored Jouf 21 May 2026 deadline not re-read; stored subject list not on Ministry page ("all except health and medical").
- us-knight-hennessy-2027: MLS listed both funded and ineligible in source.
- cn-csc-2026: funding_type stored "full" - review (a university page says partial awards may cover only some components). cn-bgs-2026: stored university Beijing Normal unsupported.
- ch-swiss-excellence-2027: stored nationality list older than the 26 Oct 2026 A-Z list; re-check.
- de-daad-scholarship-database: stored milestones 31 Aug 2026 / Feb 2027 unconfirmed.
- sa-kaust: official programme list differs from stored (Crop Science..., Life Sciences...). MS length stated two ways.
- NL scholarship: 2027-28 round now open on Study in NL (stored 2026-27); stored 11 research universities vs 10 listed; HKU name.
- rs-world-in-serbia: deadline 1 June 2026 vs 26 June on programme page; stored institution list label 2024.
## Loader policy
- Do not let a reload lower stored named-programme counts (cn-pku-grad-2026: stored 40 majors vs new 25) - keep the larger set.
- cn-sjtu-grad-2026 / cn-zju-doctoral-2026: stored cycles are 2026 but the official guides online are 2027 intake (SJTU applications open 5 Nov 2026; ZJU Dec 2026-Feb 2027): decide whether to roll these records to 2027 and update dates. Files carry 2027 facts.
- tr-turkiye-burslari-2026: stored data was wrong in 3 places (TOEFL/IELTS are Merit conditions; associate stipend 6,500 TL; tuition covered) - file fixes them; graduate housing allowance and Success Scholarship month conflict between pages.
- eu-erasmus-mundus-jm: only generic requirements (set per programme); 226 programmes in catalogue vs 218 stored.
- Hungary/Poland/Italy files are built on 2026/27 calls (no 2027/28 call published yet) and say so.
- cn-pku-grad: file replaces 40 stored majors with 25 named.
- gb-commonwealth-masters-2027: applications closed 16:00 BST 20 Oct 2026; stored milestones out of date.
- gb-gates-cambridge-2027: Round 2 deadline 8 Dec 2026 or 6 Jan 2027 depending on course; interview exists - refine milestones.
- gb-rhodes-2028: stipend GBP 21,060 (1,755/month) 2026-27 per FAQ vs stored 20,400; a bachelor route (Second BA) exists but stored levels are master/doctorate.
- cn-marine-2026: age limits conflict (Tongji 2026: under 35 master/40 doctoral; older notice and Xiamen: 40/45); stored 40/45 from older notice. Xiamen table hard to read - check level assignments.
- kr-gks-2027: graduate call text read from NIIED document hosted on Italian MFA site (not funder host) - review; UIC department rows ambiguous.
- au-melbourne-research-scholarship-2027: whole unimelb.edu.au returns 403 to automated reading; file REJECTED (moved to scratchpad\rejected); stored data left alone. Needs a person with a normal browser.
- gb-commonwealth-phd-2027: closing-date year not printed on the page; stored "child allowance" was wrong (family allowance rates corrected). gb-commonwealth-shared-2027: stored "warm clothing allowance" not on official pages (left out); "applications open 10 Nov" superseded by "now open".
- ca-ubc-international-scholars-2027: date conflict (portal: closes 2 Dec 2026 12:00 PT; programme page: 5 Oct); Vantage One Excellence Award has its own 15 Nov 2026 deadline.
- Oxford central pages (ox.ac.uk) return 403 to automated reading: gb-clarendon-2027 and gb-weidenfeld-hoffmann-2027 are thinner (built from faculty/college pages + Trust site); stored facts marked carried over. Candidates for a human browser pass, also gb-reach-oxford, gb-marshall (check reports).
- gb-cambridge-trust-international-2027: 2027/28 listing names PhD only; stored Master's+PhD - decide whether to drop Master's. Visa/health surcharge "covered" vs awardee guidance says most pay.
- Cambridge Trust: deadlines 23:59 GMT (Trust) vs 11:59pm UK time (University) - same instant in winter.
- gb-reach-oxford-2027: all Oxford pages 403; file below bar -> REJECTED (scratchpad\rejected); stored data left alone; needs a person with a normal browser (also Melbourne). 
- gb-edinburgh-doctoral-college-2027: stored "3.5 years for all" wrong (3-4 years by School); scholarship closing dates 16 Nov 2026 - 2 Feb 2027; 9 Nov 2026 admission deadline (Health School only) has passed.
- gb-imperial-presidents-phd-2027: Round 1 (2 Nov 2026) closed.
- gb-marshall-2027: 2028 cycle opens "summer 2027"; file uses the 2027 cycle facts (applicant deadline 29 Sep 2026 passed).
- se-si-scholarship-global-professionals-2027: SI calls the award "fully-funded"; stored funding type "full" should be reviewed (components listed instead). Eligible-programme list due 13 Nov 2026 not yet on page.
- es-aecid-africa-med-master-2026: call makes beneficiary pay master's enrolment cost -> tuition now "not covered" (stored said covered?); results date is 10 Sept.
- ru-government-quota-2026: portal contradicts itself (at least six vs up to six universities).
- fr-eiffel-excellence-2027: institutions' deadline 6 Jan 2027, results from 12 April 2027 (brochure says March).
- cyprusaid-unic: Spring 2027 deadline 6 Nov 2026 passed (show closed). Aalto: stored document deadline 15:00 -> 23:59 UTC+2. Helsinki acceptance time differs between pages. VLIR-UOS: Mali added per call page but country list has 25 without Mali.
- id-knb-2026: stored age limits wrong (official 21/35/40 not 25/35/40); host universities 47 in 2026, stored list (34) is the 2025 one - update the stored universities field from institutions_note.
- tw-moe-taiwan-scholarship-2026: GPA check applies to every office (stored said US-office rule); new for 2026: bachelor allowance runs to 30 June of graduation year. tw-icdf: stored said amounts unknown; now NT$15,000/18,000/20,000 by level; 2027 window 1 Dec 2026 - 15 Mar 2027 on funder page.
- hk-hkpfs-2027: HKU says both steps close 12:00 noon 1 Dec 2026 vs RGC/PolyU 11:59 pm; 400 fellowships (RGC) vs about 300 (HKU).
- pk-hec-allama-iqbal-central-asia: master/doctorate marked unconfirmed (only intro mentions them). za-mandela-rhodes-2028: only partly refreshed (foundation site returned nothing) - carried-over facts marked.
- Brunei: programme lists are from earlier sessions (UBD 2025/26, UTB 2024, KUPU SB 2023/24).
- uz-icesco: Green University's general English min IELTS 6.0 vs 5.5 on ICESCO page. kz-state: GPA min 3.0 (Bolashak) vs 2.33 (MoE page); stored "allowance partly covers accommodation" not on pages.
- jp-unesco-keizo-obuchi: deadline 30 Sep 2026 (guide) vs 15 Oct 2026 (programme page, extended). pl-unesco: 2025 edition, closed. it-ictp: 28 Feb 2027 date probably next intake.
- il-mfa-scholarship-2026: gov.il 403, embassy pages reset; no file written; page now seems to be 2027-28 round (deadline 15 Jan 2027). Needs a person with a normal browser.
- za-uct-international-refugee-pg-2027: older UCT page says 2026 call "will not open"; newer hub/handbook give 30 Aug deadline: confirm with UCT that a 2027 call exists.
- us-rotary-peace-fellowship-2028: Rotary site blocks automated reading; built from partner university pages; stored funding type "full" should be reviewed; counts (50 master's/120 certificate) carried over.
- us-jjwbgsp-2027: stored nationality list lacks Kosovo (in Annex 1).
- ph-adb-japan: source counts differ (27 vs 29 institutions; 130 vs 135 awards).
- br-pec-g-2027: gov.br blocks automated reading; 2027 facts from embassy guide; milestones in stored data conflict (docs 2-30 June, first round 14 Sep, third round 16 Nov per embassy guide vs stored 17 July/28 Sep/23 Nov); previous-year notice lists 74 countries vs stored 76. Parts from previous-year notice (marked).
- br-pec-pg-2027: stored "free place" claim not confirmed - tuition set to not covered; parts from CAPES Edital 12/2025 (previous cycle).
- cl-agcid (both): platform deadline 30 Oct 2026 23:59 Chile time (passed); weights listed add up to 90% though call says 100%; priority areas differ between call and web page.
- mx-amexcid: call closed 22 June 2026 3pm Central Mexico time; nationalities list carried over.
- au-australia-awards-2027: dfat.gov.au unreachable (timeouts); built from the Nov 2025 Policy Handbook (hosted by Australia Awards Vanuatu) and country sites for 14 programmes; many countries not read (listed in file); rates may have changed (DFAT page "Updated June 2026" could not be opened).
- au-research-training-program-2027: national stipend rates not re-read; ANU AUD 40,475 (2027), UQ AUD 39,220 (2026) vs stored national 35,550/55,537 (carried over, flagged).
- ca-cgrs-doctoral-2027: deadline pages conflict (NSERC 17 Oct; SSHRC 19 Oct 2026 direct / 7 Dec institutions; CIHR 23 Nov); stored "replaces Vanier" not found.
- ca-pearson-2027: about 37 vs 38 awards on two pages; dollar value not stated.
- kz-kimep-international: GPA 3.50 per semester vs cumulative unclear.


## Decisions for the owner

- Whether to roll the SJTU and ZJU records to the 2027 intake.
- Whether the "full" funding type on cn-csc-2026, se-si-scholarship, us-rotary-peace-fellowship (and others) should stay: none is labelled "Fully funded" (none is verified), but the stored type says full.
- Closing dates and milestones listed above (most are for cycles that have since closed or whose dates moved).