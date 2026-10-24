# Loading scholarships and universities from a spreadsheet

Admin panel, **Editorial, Imports**. Three kinds of file are accepted: **Scholarships**, **Universities** and **Degree programmes**.

## Steps

1. **Download a template** (button on the Imports page). Each kind comes as an Excel workbook and as a plain CSV.
   The Excel workbook has four sheets: *Fill in here* (the header only, this is the sheet that is read), *How to fill* (every column explained, required or not), *Example* (one example row) and *Codes* (the exact country, degree, subject and other codes the importer accepts).
2. **Fill in one row per item.** Use official sources only, give the page you used and the day you checked it, and leave a cell empty rather than guess. Put no student or personal data in the file.
3. **Upload a file**, choose what you are importing, and read the preview: how many rows are valid, what will be created, what will be updated, and the reason for every refused row. *Download error file* gives the refused rows with their reasons so you can fix and upload again.
4. **Commit.** Only valid rows are applied; the rest are skipped. Running the same file again changes nothing.
5. **Publish yourself.** Nothing goes live from an import.

## Order

Import **universities first**, then their **degree programmes**: each programme names a university that must already exist (spell the name the same way). Scholarships stand alone.

## What each kind does

| Kind | Identified by | New items | Items that already exist |
|---|---|---|---|
| Scholarships | `external_key`, or title + year + term | Draft cycle, goes through the normal review | Draft cycles are updated; live or in-review cycles are never touched |
| Universities | The university's name | Created hidden (not public) | Only empty fields are filled; nothing already entered is overwritten and public/hidden stays as it is |
| Degree programmes | University + level + programme name + teaching language | Created hidden (not public) | Only empty fields are filled; a programme already marked open to CSC applicants stays so |

To make hidden universities or programmes public: Reference data, **Universities** (or **University programmes**), tick the rows, **Show on the public pages**.
The public China university pages only list a university that has a `csc_key` (the official study-in-China system's own identifier); a university without one stays in the admin only.

## Limits

Up to 5,000 rows and 5 MB per file. Excel files are read from the first sheet; formulas are read as the value Excel stored. Dates must be written as `YYYY-MM-DD`.
