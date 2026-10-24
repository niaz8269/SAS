# International scholarship data pass, 24 October 2026

Twelve research passes read official pages (funder, government, embassy, host university) for eligibility, funding and documents. Nothing was taken from aggregators. Where an official page did not say something, the field was left as "not confirmed".

## The 55 existing scholarships (loaded into the live listings)

| Detail | Before | After |
|---|---|---|
| Eligibility notes | 0 | 55 |
| Age and experience notes | 0 | 50 |
| Funding components | 0 | 54 |
| Required documents | 0 | 51 |
| Numeric criteria (grade, language, age) | 0 | 35 |
| Funding type known | 41 | 54 |
| Open to any nationality | 0 | 20 (16 with excluded countries) |
| Explicit list of eligible countries | 4 | 11 |
| Subjects set (any or a list) | 0 | 17 |

24 scholarships still have nationality **not confirmed**: their official pages describe a region, a government nomination or a country list that could not be read. They are not matched by the nationality filter, by design.

Only future closing dates were applied (one changed). Opening dates were not applied: some researched ones belonged to older calls and would have replaced newer recorded ones.

## New scholarships (66 drafts, not published)

Australia, Canada, UK and Ireland, continental Europe, Asia, Africa and the Americas, and global funders (World Bank, ADB, IsDB, Rotary, Humphrey, AAUW, OAS, UNESCO, ICTP, Mastercard Foundation). They are **drafts**: each needs your review. After review, publish them with the recorded-authority command:

```
php artisan sas:publish-drafts <your staff email> --reason="Reviewed by the owner" [--only=external-key ...]
```

Not imported (22): discontinued or paused (Vanier, Ontario Trillium, IDRC awards, Destination Australia, OPEC Fund scholarship, OWSD PhD), no readable official page (Edinburgh Global Research and Online Learning, SINGA, Morocco AMCI, Egypt, Argentina/Uruguay, Vietnam), not a scholarship open to individuals or not university level (JICA Development Studies Program, ASEAN secondary-school scholarship), no scholarship exists (Finnish, Norwegian, Greek government), and one duplicate (Mastercard Foundation Scholars).

## Judgement calls to check

- Tsinghua, Shanghai Jiao Tong and Zhejiang are recorded as open to any nationality except Chinese citizens: the universities' wording is "non-Chinese citizens". Change it if you want those lists stricter.
- The China Scholarship Council's own sites blocked every automated request. The CSC, Silk Road, Type B, Bilateral, CLEC and Marine entries rest on host-university guides and Chinese embassy notices. Re-check them against campuschina.org by hand.
- ICCR (India) was read from a copy of the 2026-27 guidelines hosted by an Indian embassy; the ICCR portal could not be reached. Embassy notices give different age limits (40 and 50) from the guidelines (35 and 40); the guidelines were used.
- Stipendium Hungaricum's own site showed a maintenance page; the Hungarian embassy pages were used.
- Several calls closed recently or close within days (Chile AGCID 30 October 2026, Slovak NSP 31 October 2026, Eiffel 6 January 2027). The listings show these dates; the platform marks calls closed on its own once the time passes.
- Criteria that differ by degree level or route (Türkiye's minimum grades, Azerbaijan's age limits) are kept in the eligibility notes, not as automatic rules.
- Older funding summaries were written before the funding components were researched, so a few read less certain than the component table beside them (for example Türkiye Burslari says tuition "is not explicitly mentioned" while the components list it as covered). Edit the summary when you review the page. Three summaries that said eligibility "was not listed" were trimmed because eligibility is now on the page.
- Vietnam, Norway, Finland and Greece have no government scholarship for international degree students on the official pages checked; they are listed as "not available" rather than invented.

## Files

The raw research (JSON, one file per batch) and the per-scholarship reports are kept outside the repository. Re-running an import is safe: lists and components given in a file replace those stored, and nothing is duplicated.
