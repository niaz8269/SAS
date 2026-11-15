# Gap research brief (2026-11-15)

Project: SAS, a scholarship site for students. Repo `C:\Users\82335\Projects\sas`. The owner has allowed **unofficial sources** (embassy and university pages first, then reputable scholarship sites, blogs, aggregators) to fill gaps, **provided every sentence is rewritten in your own words** (no copied sentences or close paraphrase of a sentence; short factual names, numbers, dates and programme names are fine). Official pages stay preferred when you can open them.

## Rules that still hold
- **Never invent.** If you cannot find a fact, leave it out. "Not announced" is a valid result. No guessing dates from last year's pattern, no filling a gap from memory.
- Prefer a fact confirmed by two independent sources. If only one unofficial source says it, say so in `source_text` ("one scholarship site reports ...").
- Label every source: official (funder, government, embassy, the university) or unofficial. Unofficial sources are listed with `publisher_type` `aggregator` in majors files, and mentioned as "reported by ..." in the date text.
- Do not use the Chrome browser tools (they belong to the user's open browser). Use WebSearch and WebFetch only. If a page blocks you, look for another page that carries the same facts.
- Do not edit anything under `app/`, `resources/`, `tests/`, `database/migrations/`. Only write the JSON files named below. Do not run git.
- Do not write files outside the paths named below.

## What to look for in each assigned scholarship
Each scholarship is named by its `external_key` with the gaps found by an audit of the live data (language, nationalities, dates, programmes). Research only the gaps listed for it.
- `lang`: the language of teaching/study (for example "English", "English or Chinese depending on the programme").
- `nat`: which nationalities may apply. If the call names specific eligible countries (a list), give ISO 3166-1 alpha-2 codes. If it is open to applicants from any country except the host country, say `any_nationality: true`. If eligibility is by region ("developing countries"), only list countries the source names; otherwise leave it out.
- `dates`: when applications for the **next or current** call open and close, interview/result dates, as publicly reported. Only the latest call. If the next call is not announced, add nothing.
- `progs`: a list of eligible programmes/majors/courses where one is published anywhere (the programme's own pages, partner universities, embassy annexes, directories). Skip it if the scholarship is open to any subject, or if the list would be tens of thousands of rows from a commercial directory.

## Output 1: gap-fix file (one per scholarship that has something to add)
Path: `C:\Users\82335\Projects\sas\database\data\gap-fixes\2026-11-15\<external_key>.json`
```json
[{
  "external_key": "xx-example-2027",
  "teaching_language": "English",
  "any_nationality": true,
  "nationality_codes": ["PK", "IN"],
  "milestones": [{
    "kind": "opens | interview | test | results | other",
    "label": "Applications open for the 2027 call",
    "date_type": "exact | date_only | month | range | not_announced | route_dependent",
    "starts_on": "2026-12-01",
    "ends_on": null,
    "time_text": "December 2026",
    "source_text": "Your own words: what the source says and who reports it (official or unofficial).",
    "source_url": "https://..."
  }]
}]
```
- Leave out keys you have nothing for. Use either `nationality_codes` or `any_nationality`, not both.
- A **closing date** is a milestone with `kind: "other"` and a label that starts with "Application deadline". Put "(reported by <site>, not confirmed by the funder)" in the label when the source is unofficial. `month` precision (starts_on = first of that month, time_text = "March 2027") is fine when only a month is known.
- Only dates for the latest call (not past years). The importer never changes the status, a confirmed deadline or the funding type.

## Output 2: majors file (only for scholarships with a real list of programmes)
Path: `C:\Users\82335\Projects\sas\database\data\scholarship-details\2026-11-15-majors\<external_key>.json`
Same format as the existing files in `database\data\scholarship-details\2026-11-14-majors\` (read one small example, for instance `gb-gates-cambridge-2027.json` or `jp-unesco-keizo-obuchi-2027.json`): `external_key`, `checked_on` ("2026-11-15"), `sources` (each `url`, `publisher`, `publisher_type` one of government/university/funder/embassy/aggregator, `note` in your own words), `subject_rule` (own words), `any_subject`, `levels` (each `code` bachelor/master/doctorate/diploma, `confirmed`, `programmes` with `name`, `institution`, `detail`, `source_text` empty string, `url`), `notes`, `not_stated`, `fetch_problems`.
Programme and university names are facts and are copied as they are; any description (`detail`, notes) is your own words.

## Final message
Reply in under 200 words: for each scholarship, which of lang/nat/dates/progs you filled, and which you could not find (one short reason). No file contents.
