<?php

namespace App\Domain\Editorial;

use App\Models\Country;
use App\Models\DegreeLevel;
use App\Models\Subject;
use App\Models\UniversityProgramme;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The files staff fill in for the import tool. Three kinds: scholarships, universities and degree programmes.
 * Each template is offered as an Excel workbook (a sheet to fill in, a sheet that explains every column, a sheet with the exact codes
 * the importer accepts and one example row) and as a plain CSV with the header row and the example row.
 */
class ImportTemplates
{
    public const KINDS = ['scholarships' => 'Scholarships', 'universities' => 'Universities', 'programmes' => 'Degree programmes'];

    /** @return list<string> */
    public static function columns(string $kind): array
    {
        return match ($kind) {
            'universities' => CatalogueImporter::UNIVERSITY_COLUMNS,
            'programmes' => CatalogueImporter::PROGRAMME_COLUMNS,
            default => ScholarshipImporter::COLUMNS,
        };
    }

    /** @return array<string,string> column => example value */
    public static function example(string $kind): array
    {
        $values = match ($kind) {
            'universities' => ['Example University', '示例大学', 'CN', 'Example Province', 'Example City', 'https://www.example.edu.cn', 'Short factual introduction taken from the university\'s own site.',
                'https://www.example.edu.cn/international', '2026-10-01', ''],
            'programmes' => ['Example University', 'bachelor', '计算机科学与技术', 'Computer Science and Technology', 'English', '4', '26000', 'yes'],
            default => ['example-award-2028', 'Example Award', 'Example Foundation', 'DE', '2028', 'Autumn', 'partial', 'Tuition covered; stipend not confirmed.',
                'English', 'on_campus', 'master|doctorate', 'DE', 'ANY', 'engineering|computer-science', 'Short factual summary.',
                'https://example.org/call', 'Example Foundation', 'funder', '2026-10-01', 'Funder portal', 'https://example.org/apply',
                'exact', '2028-01-31 17:00', 'Europe/Berlin', '', 'Published as 17:00 CET'],
        };

        return array_combine(self::columns($kind), $values);
    }

    public static function csv(string $kind): string
    {
        if ($kind === 'scholarships') {
            return ScholarshipImporter::templateCsv();
        }

        return ScholarshipImporter::csvLine(self::columns($kind)).ScholarshipImporter::csvLine(array_values(self::example($kind)));
    }

    /** @return array<string,array{0:bool,1:string}> column => [required, what to write] */
    public static function help(string $kind): array
    {
        return match ($kind) {
            'universities' => [
                'name' => [true, 'The university\'s official English name, as on its own website. It is also how programmes find it, so spell it the same way in the programmes file.'],
                'name_zh' => [false, 'The name in Chinese characters, if it has one.'],
                'country_code' => [true, 'Two-letter ISO country code, for example CN. See the Codes sheet.'],
                'province' => [false, 'Province or region, in English.'],
                'city' => [false, 'City, in English.'],
                'website' => [false, 'The university\'s own website, starting with https://'],
                'summary' => [false, 'A short factual introduction in your own words. Leave empty if you have nothing checked.'],
                'source_url' => [true, 'The official page the information comes from (the university\'s own site or a government list).'],
                'source_checked_on' => [true, 'The day you checked that page, as YYYY-MM-DD. It cannot be in the future.'],
                'csc_key' => [false, 'Only for universities on the official study-in-China system: its own identifier for the university. Without it the university is kept in the admin but not shown on the public China university pages.'],
            ],
            'programmes' => [
                'university' => [true, 'The university\'s name exactly as in the universities file (or its address-style name such as example-university). The university must already exist, so import universities first.'],
                'level' => [true, 'bachelor, master, doctorate, non_degree or associate.'],
                'name_zh' => [false, 'The programme name in the language it is taught or listed in (Chinese characters for Chinese programmes). Fill this or name_en.'],
                'name_en' => [false, 'The programme name in English. Fill this or name_zh.'],
                'teaching_language' => [false, 'For example English, Chinese, or Chinese and English.'],
                'duration_years' => [false, 'Length in years, for example 4 or 2.5.'],
                'tuition_cny' => [false, 'Tuition per academic year in Chinese yuan, whole numbers only. Leave empty if the source gives none: nothing is assumed.'],
                'csc_eligible' => [false, 'yes if the official list marks the programme as open to Chinese Government Scholarship applicants, otherwise no or empty.'],
            ],
            default => [
                'external_key' => [false, 'Your own stable identifier for this call (for example csc-bachelor-2027). Re-importing the same key updates the same call instead of making a copy.'],
                'programme_title' => [true, 'The scholarship\'s name.'],
                'funder_name' => [false, 'Who pays for it.'],
                'host_country_code' => [false, 'Two-letter ISO code of the country that runs it.'],
                'cycle_year' => [true, 'The intake year, four digits, between 2020 and 2060.'],
                'intake_term' => [false, 'For example Autumn.'],
                'funding_type' => [true, 'full, partial, tuition_only, stipend_only, other or unknown. Use unknown when the source does not say; "fully funded" is only set by a reviewer later.'],
                'funding_summary' => [false, 'What is covered, in the source\'s own terms.'],
                'teaching_language' => [false, 'For example English.'],
                'delivery_mode' => [false, 'on_campus, online, hybrid or unknown.'],
                'degree_codes' => [false, 'Degree codes separated by | (see the Codes sheet).'],
                'destination_codes' => [false, 'Country codes of the study destinations, separated by |.'],
                'nationality_codes' => [false, 'ANY when open to every nationality, otherwise country codes separated by |.'],
                'subject_slugs' => [false, 'Subject slugs separated by | (see the Codes sheet).'],
                'summary' => [false, 'A short factual summary.'],
                'source_url' => [true, 'The official page the call is published on.'],
                'source_publisher' => [true, 'Who publishes that page.'],
                'source_publisher_type' => [true, 'funder, government, embassy, university or aggregator.'],
                'source_checked_on' => [true, 'The day you checked the page, YYYY-MM-DD, not in the future.'],
                'route_name' => [false, 'Name of the application route, for example Funder portal.'],
                'application_url' => [false, 'Where to apply, starting with https://'],
                'deadline_type' => [true, 'exact, date_only, rolling, not_announced or route_dependent. Never guess a time: use date_only when the source gives a date without a time.'],
                'deadline_local_datetime' => [false, 'Only for exact: YYYY-MM-DD HH:MM in the time zone below.'],
                'deadline_timezone' => [false, 'Only for exact: an IANA zone such as Asia/Shanghai.'],
                'deadline_date' => [false, 'Only for date_only: YYYY-MM-DD.'],
                'deadline_source_text' => [false, 'The deadline exactly as the source words it.'],
            ],
        };
    }

    /** @return array<string,list<array{0:string,1:string}>> sheet section => [code, meaning] rows */
    public static function codes(): array
    {
        return [
            'Country codes (ISO)' => Country::orderBy('name')->get(['code', 'name'])->map(fn ($c) => [(string) $c->code, (string) $c->name])->all(),
            'Degree codes' => DegreeLevel::orderBy('sort')->get(['code', 'name'])->map(fn ($d) => [(string) $d->code, (string) $d->name])->all(),
            'Subject slugs' => Subject::orderBy('name')->get(['slug', 'name'])->map(fn ($s) => [(string) $s->slug, (string) $s->name])->all(),
            'Programme levels' => collect(UniversityProgramme::LEVELS)->map(fn ($label, $key) => [$key, $label])->values()->all(),
            'Funding types' => [['full', 'Full'], ['partial', 'Partial'], ['tuition_only', 'Tuition only'], ['stipend_only', 'Stipend only'], ['other', 'Other'], ['unknown', 'Not stated']],
            'Deadline types' => [['exact', 'Date and time'], ['date_only', 'Date only'], ['rolling', 'Rolling'], ['not_announced', 'Not announced'], ['route_dependent', 'Depends on the route']],
            'Publisher types' => [['funder', 'Funder'], ['government', 'Government'], ['embassy', 'Embassy'], ['university', 'University'], ['aggregator', 'Aggregator']],
        ];
    }

    /** Writes the Excel workbook to a new temporary file and returns its path. */
    public static function xlsx(string $kind): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sas-tpl-').'.xlsx';
        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 24;
        $writer = new Writer($options);
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();
        $columns = self::columns($kind);

        // Sheet 1: header row only, as text cells so Excel never turns a code or a date into something else.
        $writer->getCurrentSheet()->setName('Fill in here');
        $writer->addRow(Row::fromValues($columns, $bold));

        $writer->addNewSheetAndMakeItCurrent()->setName('How to fill');
        $writer->addRow(Row::fromValues(['Column', 'Required', 'What to write'], $bold));
        foreach (self::help($kind) as $column => [$required, $text]) {
            $writer->addRow(Row::fromValues([$column, $required ? 'Yes' : 'No', $text]));
        }
        $writer->addRow(Row::fromValues([]));
        foreach ([
            'Fill one row per '.match ($kind) {
                'universities' => 'university', 'programmes' => 'degree programme', default => 'scholarship call'
            }.' on the first sheet, under the header. Do not rename or reorder the columns.',
            'Use only information from official sources and always give the page and the day you checked it. Leave a cell empty rather than guessing.',
            'When you are done, upload the file on Imports, choose "'.self::KINDS[$kind].'" and read the preview. Nothing goes live from an import: new items arrive as drafts or hidden, and you publish them yourself.',
            'Do not put student or personal data in these files.',
        ] as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Example');
        $writer->addRow(Row::fromValues($columns, $bold));
        $writer->addRow(Row::fromValues(array_values(self::example($kind))));

        $writer->addNewSheetAndMakeItCurrent()->setName('Codes');
        foreach (self::codes() as $title => $rows) {
            $writer->addRow(Row::fromValues([$title], $bold));
            foreach ($rows as [$code, $meaning]) {
                $writer->addRow(Row::fromValues([$code, $meaning]));
            }
            $writer->addRow(Row::fromValues([]));
        }
        $writer->close();

        return $path;
    }
}
