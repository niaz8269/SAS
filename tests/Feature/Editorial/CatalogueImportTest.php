<?php

namespace Tests\Feature\Editorial;

use App\Domain\Editorial\CatalogueImporter;
use App\Domain\Editorial\ImportTemplates;
use App\Domain\Editorial\ScholarshipImporter;
use App\Domain\Editorial\SpreadsheetFile;
use App\Enums\Role;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Models\Country;
use App\Models\University;
use App\Models\UniversityProgramme;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CatalogueImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
    }

    private function csv(string $kind, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cat');
        $fh = fopen($path, 'w');
        fputcsv($fh, ImportTemplates::columns($kind), ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', ImportTemplates::columns($kind)), ',', '"', '');
        }
        fclose($fh);

        return $path;
    }

    private function uni(array $over = []): array
    {
        return array_merge(['name' => 'Example University', 'name_zh' => '示例大学', 'country_code' => 'CN', 'province' => 'Hubei', 'city' => 'Wuhan', 'website' => 'https://www.example.edu.cn',
            'summary' => 'A short factual introduction.', 'source_url' => 'https://www.example.edu.cn/international', 'source_checked_on' => '2026-10-01'], $over);
    }

    private function prog(array $over = []): array
    {
        return array_merge(['university' => 'Example University', 'level' => 'master', 'name_zh' => '计算机科学', 'name_en' => 'Computer Science', 'teaching_language' => 'English',
            'duration_years' => '2.5', 'tuition_cny' => '26000', 'csc_eligible' => 'yes'], $over);
    }

    public function test_every_template_has_the_columns_the_importer_reads_and_an_example(): void
    {
        foreach (ImportTemplates::KINDS as $kind => $label) {
            $columns = ImportTemplates::columns($kind);
            $csv = ImportTemplates::csv($kind);
            $lines = array_values(array_filter(explode("\r\n", $csv)));
            $this->assertSame(2, count($lines), "{$kind}: header and one example row");
            $this->assertSame($columns, str_getcsv($lines[0], ',', '"', ''), $kind);
            $this->assertSame(array_keys(ImportTemplates::help($kind)), $columns, "{$kind}: every column is explained, in order");
            $this->assertSame($columns, array_keys(ImportTemplates::example($kind)));
        }
    }

    public function test_the_excel_template_has_four_sheets_and_the_first_one_is_the_header_only(): void
    {
        $path = ImportTemplates::xlsx('universities');
        $reader = new Reader;
        $reader->open($path);
        $names = [];
        $firstRows = 0;
        foreach ($reader->getSheetIterator() as $i => $sheet) {
            $names[] = $sheet->getName();
            if ($i === 1) {
                foreach ($sheet->getRowIterator() as $row) {
                    $firstRows++;
                    $this->assertSame(CatalogueImporter::UNIVERSITY_COLUMNS, $row->toArray());
                }
            }
        }
        $reader->close();
        @unlink($path);

        $this->assertSame(['Fill in here', 'How to fill', 'Example', 'Codes'], $names);
        $this->assertSame(1, $firstRows, 'the sheet to fill in holds only the header, so nothing example-like can be imported by accident');
    }

    public function test_a_filled_in_excel_file_is_read_like_a_csv(): void
    {
        $xlsx = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($xlsx);
        $writer->addRow(Row::fromValues(ImportTemplates::columns('programmes')));
        $writer->addRow(Row::fromValues(['Example University', 'bachelor', '', 'Law', 'English', 4, 1500.5, true]));
        $writer->close();

        $csv = SpreadsheetFile::toCsv($xlsx);
        $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), file($csv, FILE_IGNORE_NEW_LINES));
        @unlink($xlsx);
        @unlink($csv);

        $this->assertSame(['Example University', 'bachelor', '', 'Law', 'English', '4', '1500.5', 'yes'], $rows[1]);
    }

    public function test_universities_arrive_hidden_and_a_second_run_changes_nothing(): void
    {
        $importer = app(CatalogueImporter::class);
        $path = $this->csv('universities', [$this->uni(), $this->uni(['name' => 'Second University', 'name_zh' => '', 'csc_key' => 'K-1'])]);

        $import = $importer->stage($path, 'u.csv', 'universities');
        $this->assertSame([2, 2, 0], [$import->total_rows, $import->valid_rows, $import->error_rows]);
        $this->assertSame(0, University::count(), 'staging writes nothing');

        $importer->commit($import);
        $u = University::where('slug', 'example-university')->firstOrFail();
        $this->assertFalse($u->published, 'an import never makes anything public');
        $this->assertSame('example.edu.cn', $u->domain);
        $this->assertSame('2026-10-01', $u->catalogue_checked_at->toDateString());
        $this->assertSame('K-1', University::where('slug', 'second-university')->value('csc_key'));

        $again = $importer->stage($path, 'u.csv', 'universities');
        $this->assertSame(2, $again->summary['updates']);
        $importer->commit($again);
        $this->assertSame(2, University::count());
    }

    public function test_an_import_only_fills_empty_fields_of_an_existing_university_and_never_changes_whether_it_is_public(): void
    {
        $existing = new University;
        $existing->forceFill(['name' => 'Example University', 'slug' => 'example-university', 'city' => 'Checked City', 'published' => true])->save();

        $importer = app(CatalogueImporter::class);
        $importer->commit($importer->stage($this->csv('universities', [$this->uni(['city' => 'Other City'])]), 'u.csv', 'universities'));

        $existing->refresh();
        $this->assertSame('Checked City', $existing->city, 'a value somebody entered is kept');
        $this->assertSame('Hubei', $existing->province, 'an empty field is filled');
        $this->assertTrue($existing->published, 'it stays public');
    }

    public function test_bad_university_rows_are_refused_with_reasons(): void
    {
        $rows = [
            $this->uni(['name' => '']), $this->uni(['name' => 'B', 'country_code' => 'ZZ']), $this->uni(['name' => 'C', 'source_url' => '']),
            $this->uni(['name' => 'D', 'source_url' => 'http://localhost/x']), $this->uni(['name' => 'E', 'source_checked_on' => '2999-01-01']),
            $this->uni(['name' => 'F', 'website' => 'javascript:alert(1)']), $this->uni(['name' => '西安交通大学']),
            $this->uni(['name' => 'G']), $this->uni(['name' => 'G']),
        ];
        $import = app(CatalogueImporter::class)->stage($this->csv('universities', $rows), 'u.csv', 'universities');
        $errors = $import->rows()->orderBy('row_number')->get()->map(fn ($r) => implode(' ', $r->errors ?? []))->all();

        $this->assertStringContainsString('name is required', $errors[0]);
        $this->assertStringContainsString("'ZZ' is not a known ISO country code", $errors[1]);
        $this->assertStringContainsString('source_url is required', $errors[2]);
        $this->assertStringContainsString('public http(s)', $errors[3]);
        $this->assertStringContainsString('future', $errors[4]);
        $this->assertStringContainsString('website must be', $errors[5]);
        $this->assertStringContainsString('Latin alphabet', $errors[6]);
        $this->assertSame('', $errors[7]);
        $this->assertStringContainsString('Duplicate of row', $errors[8]);
        $this->assertSame(8, $import->error_rows);
        $this->assertStringContainsString('row_number', CatalogueImporter::errorCsv($import));
    }

    public function test_programmes_need_an_existing_university_and_arrive_hidden(): void
    {
        $importer = app(CatalogueImporter::class);
        $path = $this->csv('programmes', [$this->prog(), $this->prog(['name_zh' => '', 'name_en' => 'Law', 'level' => 'bachelor', 'csc_eligible' => ''])]);

        $before = $importer->stage($path, 'p.csv', 'programmes');
        $this->assertSame(2, $before->error_rows);
        $this->assertStringContainsString('does not exist yet', $before->rows()->first()->errors[0]);

        $importer->commit($importer->stage($this->csv('universities', [$this->uni()]), 'u.csv', 'universities'));
        $import = $importer->stage($path, 'p.csv', 'programmes');
        $this->assertSame(2, $import->valid_rows);
        $importer->commit($import);

        $cs = UniversityProgramme::where('name_zh', '计算机科学')->firstOrFail();
        $this->assertFalse($cs->published);
        $this->assertTrue($cs->csc_eligible);
        $this->assertSame(2.5, $cs->duration_years);
        $this->assertSame(26000, $cs->tuition_cny);
        $this->assertSame('staff', $cs->name_en_source, 'an English name typed by staff is not overwritten by a later official-names import');
        $law = UniversityProgramme::where('name_zh', 'Law')->firstOrFail();
        $this->assertFalse($law->csc_eligible);

        $importer->commit($importer->stage($path, 'p.csv', 'programmes'));
        $this->assertSame(2, UniversityProgramme::count(), 'running it again adds nothing');
    }

    public function test_bad_programme_rows_are_refused(): void
    {
        app(CatalogueImporter::class)->commit(app(CatalogueImporter::class)->stage($this->csv('universities', [$this->uni()]), 'u.csv', 'universities'));
        $rows = [
            $this->prog(['level' => 'diploma']), $this->prog(['name_zh' => '', 'name_en' => '']), $this->prog(['duration_years' => '40']),
            $this->prog(['tuition_cny' => '¥26,000']), $this->prog(['csc_eligible' => 'maybe']), $this->prog(['name_zh' => '法学', 'name_en' => 'Fine']),
        ];
        $import = app(CatalogueImporter::class)->stage($this->csv('programmes', $rows), 'p.csv', 'programmes');
        $errors = $import->rows()->orderBy('row_number')->get()->map(fn ($r) => implode(' ', $r->errors ?? []))->all();

        $this->assertStringContainsString("level 'diploma'", $errors[0]);
        $this->assertStringContainsString('Fill name_zh or name_en', $errors[1]);
        $this->assertStringContainsString('duration_years', $errors[2]);
        $this->assertStringContainsString('tuition_cny', $errors[3]);
        $this->assertStringContainsString('csc_eligible', $errors[4]);
        $this->assertSame('', $errors[5]);
    }

    public function test_a_file_with_the_wrong_header_is_refused_whole(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cat');
        file_put_contents($path, "name,country\r\nX,CN\r\n");

        $import = app(CatalogueImporter::class)->stage($path, 'bad.csv', 'universities');

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Missing columns', $import->summary['error']);
    }

    public function test_the_scholarship_importer_still_works_and_imports_are_kind_scoped(): void
    {
        $import = app(ScholarshipImporter::class)->stage($this->csv('scholarships', []), 's.csv');
        $this->assertSame('scholarships', $import->kind);
    }

    public function test_staff_with_import_permission_see_the_template_downloads(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->role(Role::Administrator)->create());

        Livewire::test(ListImports::class)->assertOk()->assertSee('Download a template')->assertSee('Upload a file');
    }
}
