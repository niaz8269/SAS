<?php

namespace Tests\Feature\Editorial;

use App\Domain\Editorial\CycleWorkflow;
use App\Domain\Editorial\ScholarshipImporter;
use App\Enums\PublicationState;
use App\Models\CycleDeadline;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ScholarshipRoute;
use App\Models\SourceEvidence;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        $fh = fopen($path, 'w');
        fputcsv($fh, ScholarshipImporter::COLUMNS, ',', '"', '');
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', ScholarshipImporter::COLUMNS), ',', '"', '');
        }
        fclose($fh);

        return $path;
    }

    private function row(array $over = []): array
    {
        return array_merge([
            'external_key' => 'ext-1', 'programme_title' => 'Example Award', 'funder_name' => 'Example Foundation', 'host_country_code' => 'DE',
            'cycle_year' => '2028', 'intake_term' => 'Autumn', 'funding_type' => 'partial', 'delivery_mode' => 'on_campus',
            'degree_codes' => 'master|doctorate', 'destination_codes' => 'DE', 'nationality_codes' => 'ANY', 'subject_slugs' => 'engineering',
            'source_url' => 'https://example.org/call', 'source_publisher' => 'Example Foundation', 'source_publisher_type' => 'funder',
            'source_checked_on' => '2026-10-01', 'route_name' => 'Funder portal', 'application_url' => 'https://example.org/apply',
            'deadline_type' => 'exact', 'deadline_local_datetime' => '2028-01-31 17:00', 'deadline_timezone' => 'Europe/Berlin',
        ], $over);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
    }

    public function test_valid_rows_are_staged_then_committed_as_drafts_never_published(): void
    {
        $editor = User::factory()->role('editor')->create();
        $importer = app(ScholarshipImporter::class);

        $import = $importer->stage($this->csv([$this->row()]), 'cycles.csv', $editor);
        $this->assertSame('validated', $import->status);
        $this->assertSame([1, 0], [$import->valid_rows, $import->error_rows]);
        $this->assertSame(0, ScholarshipCycle::count(), 'staging must not touch live tables');

        $importer->commit($import, $editor);
        $cycle = ScholarshipCycle::firstOrFail();
        $this->assertSame(PublicationState::Draft, $cycle->publication_state);   // cannot publish directly
        $this->assertTrue($cycle->any_nationality);
        $this->assertFalse($cycle->fully_funded_verified);
        $this->assertSame(2, $cycle->degreeLevels()->count());
        $this->assertSame($editor->id, $cycle->created_by);
        $this->assertSame('2028-01-31 16:00:00', $cycle->currentDeadlines->first()->closes_at_utc->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audits', ['action' => 'import.committed']);
    }

    public function test_replaying_an_import_creates_no_duplicates_and_no_new_deadline_versions(): void
    {
        $editor = User::factory()->role('editor')->create();
        $importer = app(ScholarshipImporter::class);
        $file = $this->csv([$this->row()]);

        $importer->commit($importer->stage($file, 'a.csv', $editor), $editor);
        $second = $importer->stage($file, 'a.csv', $editor);
        $this->assertSame(1, $second->summary['updates']);
        $importer->commit($second, $editor);
        $importer->commit($second->fresh(), $editor); // committing the same batch twice

        $this->assertSame(1, ScholarshipProgramme::count());
        $this->assertSame(1, ScholarshipCycle::count());
        $this->assertSame(1, CycleDeadline::count());
        $this->assertSame(1, ScholarshipRoute::count());
        $this->assertSame(1, SourceEvidence::count());
    }

    public function test_a_changed_deadline_in_a_reimport_creates_a_new_version(): void
    {
        $editor = User::factory()->role('editor')->create();
        $importer = app(ScholarshipImporter::class);
        $importer->commit($importer->stage($this->csv([$this->row()]), 'a.csv', $editor), $editor);
        $importer->commit($importer->stage($this->csv([$this->row(['deadline_local_datetime' => '2028-02-28 17:00'])]), 'b.csv', $editor), $editor);

        $this->assertSame(2, CycleDeadline::count());
        $this->assertSame(1, CycleDeadline::whereNull('superseded_at')->count());
    }

    public function test_the_same_cycle_without_an_external_key_is_matched_by_programme_and_year(): void
    {
        $editor = User::factory()->role('editor')->create();
        $importer = app(ScholarshipImporter::class);
        $importer->commit($importer->stage($this->csv([$this->row(['external_key' => ''])]), 'a.csv', $editor), $editor);
        $again = $importer->stage($this->csv([$this->row(['external_key' => ''])]), 'a.csv', $editor);
        $this->assertSame(1, $again->summary['updates']);
        $importer->commit($again, $editor);
        $this->assertSame(1, ScholarshipCycle::count());
    }

    public function test_a_new_year_creates_a_new_cycle_under_the_same_programme(): void
    {
        $editor = User::factory()->role('editor')->create();
        $importer = app(ScholarshipImporter::class);
        $importer->commit($importer->stage($this->csv([$this->row(['external_key' => '']), $this->row(['external_key' => '', 'cycle_year' => '2029'])]), 'a.csv', $editor), $editor);
        $this->assertSame(1, ScholarshipProgramme::count());
        $this->assertSame(2, ScholarshipCycle::count());
    }

    public function test_duplicate_rows_in_one_file_are_flagged(): void
    {
        $import = app(ScholarshipImporter::class)->stage($this->csv([$this->row(), $this->row()]), 'dup.csv');
        $this->assertSame([1, 1], [$import->valid_rows, $import->error_rows]);
        $this->assertSame(1, $import->summary['duplicates_in_file']);
        $this->assertStringContainsString('Duplicate of row 2', implode(' ', $import->rows()->where('status', 'invalid')->first()->errors));
    }

    public function test_row_level_validation_reports_row_numbers_and_never_truncates_silently(): void
    {
        $rows = [
            $this->row(['external_key' => 'a']),
            $this->row(['external_key' => 'b', 'cycle_year' => '28', 'funding_type' => 'huge', 'source_url' => 'javascript:alert(1)']),
            $this->row(['external_key' => 'c', 'degree_codes' => 'wizard', 'deadline_type' => 'exact', 'deadline_local_datetime' => '31/01/2028', 'deadline_timezone' => 'Nowhere/Land']),
            $this->row(['external_key' => 'd', 'deadline_type' => 'date_only', 'deadline_local_datetime' => '2028-01-31 23:59', 'deadline_date' => '2028-01-31']),
            $this->row(['external_key' => 'e', 'source_checked_on' => '2999-01-01', 'application_url' => 'http://127.0.0.1/admin']),
        ];
        $import = app(ScholarshipImporter::class)->stage($this->csv($rows), 'bad.csv');

        $this->assertSame(5, $import->total_rows);
        $this->assertSame([1, 4], [$import->valid_rows, $import->error_rows]);
        $byRow = $import->rows()->where('status', 'invalid')->get()->keyBy('row_number');
        $this->assertSame([3, 4, 5, 6], $byRow->keys()->sort()->values()->all()); // header is row 1
        $this->assertStringContainsString('cycle_year', implode(' ', $byRow[3]->errors));
        $this->assertStringContainsString('funding_type', implode(' ', $byRow[3]->errors));
        $this->assertStringContainsString('public http(s)', implode(' ', $byRow[3]->errors));
        $this->assertStringContainsString("Unknown degree code 'wizard'", implode(' ', $byRow[4]->errors));
        $this->assertStringContainsString('must not carry a time', implode(' ', $byRow[5]->errors)); // no fabricated 23:59
        $this->assertStringContainsString('cannot be in the future', implode(' ', $byRow[6]->errors));

        app(ScholarshipImporter::class)->commit($import);
        $this->assertSame(1, ScholarshipCycle::count()); // only the valid row
    }

    public function test_missing_columns_fail_the_batch_with_a_clear_message(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, "programme_title,cycle_year\nX,2028\n");
        $import = app(ScholarshipImporter::class)->stage($path, 'x.csv');
        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Missing columns', $import->summary['error']);
    }

    public function test_imports_cannot_change_live_or_in_review_content(): void
    {
        $editor = User::factory()->role('editor')->create();
        $reviewer = User::factory()->role('reviewer')->create();
        $importer = app(ScholarshipImporter::class);
        $importer->commit($importer->stage($this->csv([$this->row()]), 'a.csv', $editor), $editor);

        $cycle = ScholarshipCycle::firstOrFail();
        $wf = app(CycleWorkflow::class);
        $wf->submitForReview($cycle, $editor);
        $wf->approve($cycle->fresh(), $reviewer);
        $wf->publish($cycle->fresh(), $reviewer);

        $import = $importer->stage($this->csv([$this->row(['funding_summary' => 'Changed by import', 'funding_type' => 'full'])]), 'b.csv', $editor);
        $this->assertSame(1, $import->error_rows);
        $this->assertStringContainsString('Imports cannot change live', implode(' ', $import->rows()->first()->errors));
        $importer->commit($import, $editor);
        $this->assertSame('partial', $cycle->fresh()->funding_type);
        $this->assertSame(PublicationState::Published, $cycle->fresh()->publication_state);
    }

    public function test_dangerous_urls_are_refused(): void
    {
        $i = app(ScholarshipImporter::class);
        foreach (['http://localhost/x', 'http://127.0.0.1/', 'http://10.0.0.5/', 'http://169.254.169.254/latest', 'http://[::1]/', 'file:///etc/passwd', 'ftp://example.org/x',
            'https://user:pass@example.org/', 'http://intranet/', 'https://printer.local/', 'javascript:alert(1)'] as $bad) {
            $this->assertFalse($i->safeUrl($bad), $bad);
        }
        $this->assertTrue($i->safeUrl('https://www.example.org/path?x=1'));
    }

    public function test_exports_neutralise_formula_injection(): void
    {
        $import = app(ScholarshipImporter::class)->stage($this->csv([$this->row(['programme_title' => '=HYPERLINK("http://evil","x")', 'source_url' => 'javascript:1'])]), 'f.csv');
        $csv = ScholarshipImporter::errorCsv($import);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertSame("'+1", ScholarshipImporter::neutralise('+1'));
        $this->assertSame("'@SUM(A1)", ScholarshipImporter::neutralise('@SUM(A1)'));
        $this->assertSame('plain', ScholarshipImporter::neutralise('plain'));
    }

    public function test_the_downloadable_template_itself_validates_as_a_clean_row(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, ScholarshipImporter::templateCsv());
        $import = app(ScholarshipImporter::class)->stage($path, 'template.csv');
        $this->assertSame(1, $import->valid_rows, json_encode($import->rows()->first()->errors));
    }

    public function test_utf8_bom_in_the_header_is_tolerated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bom');
        file_put_contents($path, "\xEF\xBB\xBF".ScholarshipImporter::templateCsv());
        $this->assertSame(1, app(ScholarshipImporter::class)->stage($path, 'bom.csv')->valid_rows);
    }
}
