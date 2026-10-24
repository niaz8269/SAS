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

## Study guides (51 destination countries)

Six research passes read each country's own study portal, immigration authority and education ministry. A guide holds the study system, how to apply, visa basics, living costs (only figures an official page states), working while studying and official links. Load them with `php artisan sas:import-guides file.json`; an import never makes a guide public. The 26 guides that were already public were upgraded in place; the 25 new ones are hidden until you read them and use **Countries > Show the study guide publicly**. Many official portals blocked automated access (for example Study Australia's visa pages, CSC sites, Hungary's maintenance pages), so some guides are short; each entry's notes list what is missing. Official languages were not imported from the guides (the site keeps its own list).

## Nationality rules, second pass

Three research passes read the official country lists, annexes and PDFs for the 60 scholarships whose eligible nationalities were unconfirmed. Settled and loaded: Chevening (197 countries, read through a text extraction: spot-check it against the live selector), Swiss Government Excellence (186 countries from the official A-Z table; its wording is 'country of origin', which may mean residence), Mexico AMEXCID (197), US Humphrey (147), UNESCO/Keizo Obuchi (92), UNESCO/Poland (57), Taiwan ICDF (51), Malaysia International Scholarship (101), Cambridge Trust Trailblazer (134), Fundación Carolina (20, national and resident), Korea GKS (open to all countries through the University Track; the Embassy Track uses a 74-country quota list), the Netherlands scholarship (any nationality except the 30 EEA states) and the Finnish, Danish and Romanian programmes (any except EU/EEA citizens). Left unconfirmed on purpose: scholarships whose rule is where you live, your fee status or a list the official page does not print (DAAD Helmut-Schmidt, Weidenfeld-Hoffmann, ARES, Government of Ireland, Trinity, UCD, Glasgow, Auckland, MBZUAI, Rhodes and others). Result: 37 of the 55 published scholarships now have a confirmed nationality rule (31 before); across all 121 scholarships including drafts, 81.
