# Medical universities: fees, profiles and scholarships (24 October 2026)

## The rule

A university appears in the medical section only if it has **at least one scholarship for international students** and a **checked fee structure**, both published. A university without a scholarship is not listed (its record is kept, hidden). Each university page shows: location, founding year, programme, duration, teaching language, intake, tuition, hostel, the university's other charges, the application fee, Chinese-language (HSK) rules, age, marks, documents, teaching hospitals, recognition, scholarships, and the source and date of every figure.

Only what the **university or the government charges** is shown and added up. Agency "management", "liaison" and "service" charges are never shown. Refundable deposits, optional items and alternatives (a double room instead of a triple room) are listed but not added to the totals.

## Listed now (17)

Xuzhou Medical University, Xinjiang Medical University, Capital Medical University, Hebei Medical University, Chongqing Medical University, Kunming Medical University, Nanjing Medical University, Wenzhou Medical University, Fudan University, Jinan University, Wuhan University, Guangzhou Medical University, Southern Medical University, Shantou University Medical College, Shandong University, Xi'an Jiaotong University, Guangxi Medical University.

Fees come from each university's own 2026 pages (read on 24 October 2026) and the scholarships from its own notices, with the page linked. Where the university's page agreed with the fee sheet you gave us, the official figure replaced the sheet (for example Xuzhou's hostel is CNY 4,800, not 4,600).

## Hidden, and why

| University | Reason |
|---|---|
| Harbin Medical, Qingdao, Jinzhou Medical, Dalian Medical, Shihezi, Sichuan University, NCUST, Nakhchivan State | No scholarship usable for the English medicine programme on an official page (Sichuan's "Study at SCU" scholarship excludes clinical medicine; Dalian's are postgraduate only) |
| Zhengzhou, China Medical, Beihua, Tianjin Medical | Official sites would not open, so nothing was recorded |
| Jilin | Its only award is for current self-funded students (2024 notice) |
| Jiangsu University, Southeast University, Xiamen, HUST (Tongji) | Scholarship unclear for MBBS, dated, or amounts not published; confirm with the university first |
| Anhui Medical (2022), Southwest Medical (2023), Ningxia Medical (2025) | Fee pages are too old; ask the university for current fees |
| Zhejiang University | The fee table lost its alignment in the brochure |
| Shaanxi University of Chinese Medicine | Its clinical medicine is taught in Chinese only and it is not on the Ministry of Education's English-MBBS list |
| Shandong Second Medical University | The "5,000 RMB scholarship" on the poster is not real (5,000 is the twin-room hostel fee) and no official page says the programme is English-taught |
| Guilin Medical, China Three Gorges, North Sichuan Medical College | Guilin and Three Gorges teach clinical medicine in Chinese on their official pages; North Sichuan's awards are for students already enrolled, and its fee sheet is from January 2025 |

To list a hidden one, publish its fee structure and a scholarship under **Reference data, Medical fees and University scholarships** (bulk actions "Show on the public pages").

## Things to know

- **Recognition** (WHO/WDOMS, ECFMG, PMDC, NMC) is marked "checked by SAS" only where an agent found the university in the registry and recorded a link; everything else says "stated by the university, not checked by SAS". A registry listing is not an endorsement, and a graduate must check acceptance in the country where they want to practise.
- Mistakes found in the sheets: the Three Gorges sheet prints the Chinese name of Zhengzhou University; the Sichuan University sheet contradicts itself on HSK 4 (and its 36,000 tuition was "after 20% special discount": the university's own figure is 45,000); Guangxi's sheet says it was founded in 1958 while its logo shows 1934.
- The Dalian acceptance letter is from 2019, names a student and shows bank details; none of it was stored. Dalian's official 2025 page was used instead.
- Fees change every year. Every page says so and shows the source date.
- Load more with `php artisan sas:import-university-details file.json` (format in `docs/IMPORTING.md`). An import never publishes a row unless the entry says `"published": true`.
