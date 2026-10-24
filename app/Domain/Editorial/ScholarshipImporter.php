<?php

namespace App\Domain\Editorial;

use App\Domain\Scholarship\ChangeDeadline;
use App\Domain\Scholarship\SearchIndexer;
use App\Enums\DeadlineType;
use App\Enums\PublicationState;
use App\Models\Country;
use App\Models\CycleDeadline;
use App\Models\DegreeLevel;
use App\Models\Funder;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\ScholarshipRoute;
use App\Models\SourceEvidence;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * IMP-01..03. Staff CSV import: stage -> validate -> preview -> commit.
 * - Never publishes: imported cycles are created as Draft and still go through review.
 * - Idempotent: a cycle is identified by external_key (or programme slug + cycle year); replays update, never duplicate.
 * - Live content (in review / approved / published) is never changed by an import.
 * - Contains no student data and performs no network fetching (so there is no SSRF surface in R1).
 */
class ScholarshipImporter
{
    public const SCHEMA_VERSION = '1';

    public const MAX_ROWS = 5000;

    public const COLUMNS = [
        'external_key', 'programme_title', 'funder_name', 'host_country_code', 'cycle_year', 'intake_term', 'funding_type', 'funding_summary',
        'teaching_language', 'delivery_mode', 'degree_codes', 'destination_codes', 'nationality_codes', 'subject_slugs', 'summary',
        'source_url', 'source_publisher', 'source_publisher_type', 'source_checked_on', 'route_name', 'application_url',
        'deadline_type', 'deadline_local_datetime', 'deadline_timezone', 'deadline_date', 'deadline_source_text',
    ];

    public static function templateCsv(): string
    {
        $example = ['example-award-2028', 'Example Award', 'Example Foundation', 'DE', '2028', 'Autumn', 'partial', 'Tuition covered; stipend not confirmed.',
            'English', 'on_campus', 'master|doctorate', 'DE', 'ANY', 'engineering|computer-science', 'Short factual summary.',
            'https://example.org/call', 'Example Foundation', 'funder', '2026-10-01', 'Funder portal', 'https://example.org/apply',
            'exact', '2028-01-31 17:00', 'Europe/Berlin', '', 'Published as 17:00 CET'];

        return self::csvLine(self::COLUMNS).self::csvLine($example);
    }

    // ------------------------------------------------------------------ stage

    /** Parses and validates every row; nothing is written to scholarship tables. */
    public function stage(string $path, string $filename, ?User $by = null): Import
    {
        $import = Import::create(['user_id' => $by?->id, 'kind' => 'scholarships', 'filename' => basename($filename), 'schema_version' => self::SCHEMA_VERSION, 'status' => 'staged']);

        $handle = fopen($path, 'r');
        if (! $handle) {
            return $this->fail($import, 'The file could not be read.');
        }
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle); // no BOM: start from the beginning
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);

            return $this->fail($import, 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(self::COLUMNS, $header);
        if ($missing) {
            fclose($handle);

            return $this->fail($import, 'Missing columns: '.implode(', ', $missing).'. Download the template and keep its header row.');
        }

        $seen = [];
        $rowNumber = 1;
        $valid = $errors = $dups = 0;
        $maps = $this->lookups();

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($rowNumber - 1 > self::MAX_ROWS) {
                $this->fail($import, 'Too many rows (max '.self::MAX_ROWS.'). Split the file.');
                fclose($handle);

                return $import;
            }
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            if (count($cells) !== count($header)) {
                ImportRow::create(['import_id' => $import->id, 'row_number' => $rowNumber, 'payload' => [], 'status' => 'invalid',
                    'errors' => ['Row has '.count($cells).' columns, expected '.count($header).' (check quoting and commas).']]);
                $errors++;

                continue;
            }
            $row = array_map(fn ($v) => trim((string) $v), array_combine($header, $cells));
            $row = array_intersect_key($row, array_flip(self::COLUMNS));
            $key = $this->identity($row);

            $problems = $this->validateRow($row, $maps);
            if (isset($seen[$key])) {
                $problems[] = "Duplicate of row {$seen[$key]} in this file (same cycle identity '{$key}').";
                $dups++;
            }
            $seen[$key] ??= $rowNumber;

            $existing = $this->findCycle($key, $row);
            $action = $existing ? 'update' : 'create';
            if ($existing && ! in_array($existing->publication_state, [PublicationState::Draft, PublicationState::ChangesRequested], true)) {
                $problems[] = "This cycle is '{$existing->publication_state->label()}'. Imports cannot change live or in-review content; edit it in the editor.";
            }

            ImportRow::create([
                'import_id' => $import->id, 'row_number' => $rowNumber, 'external_key' => $key, 'payload' => $row,
                'status' => $problems ? 'invalid' : 'valid', 'action' => $problems ? null : $action, 'errors' => $problems ?: null,
            ]);
            $problems ? $errors++ : $valid++;
        }
        fclose($handle);

        $import->update([
            'status' => 'validated', 'total_rows' => $valid + $errors, 'valid_rows' => $valid, 'error_rows' => $errors,
            'summary' => ['creates' => $import->rows()->where('action', 'create')->count(), 'updates' => $import->rows()->where('action', 'update')->count(), 'duplicates_in_file' => $dups],
        ]);
        AuditLog::record('import.staged', $import, null, ['rows' => $valid + $errors, 'errors' => $errors], null, $by);

        return $import;
    }

    // ----------------------------------------------------------------- commit

    /** Applies the valid, not-yet-applied rows. Safe to run repeatedly. */
    public function commit(Import $import, ?User $by = null): Import
    {
        if (! in_array($import->status, ['validated', 'committed'], true)) {
            return $import;
        }
        $maps = $this->lookups();

        $import->rows()->where('status', 'valid')->where('applied', false)->orderBy('row_number')->chunkById(100, function ($rows) use ($maps, $by) {
            foreach ($rows as $r) {
                try {
                    DB::transaction(function () use ($r, $maps, $by) {
                        $this->apply($r->payload, $maps, $by);
                        $r->update(['applied' => true]);
                    });
                } catch (\Throwable $e) {
                    $r->update(['status' => 'invalid', 'errors' => ['Could not be applied: '.Str::limit($e->getMessage(), 200)]]);
                }
            }
        });

        $import->update([
            'status' => 'committed', 'committed_at' => now(),
            'valid_rows' => $import->rows()->where('status', 'valid')->count(), 'error_rows' => $import->rows()->where('status', 'invalid')->count(),
        ]);
        AuditLog::record('import.committed', $import, null, ['applied' => $import->rows()->where('applied', true)->count()], null, $by);

        return $import->fresh();
    }

    private function apply(array $row, array $maps, ?User $by): void
    {
        $key = $this->identity($row);
        $programmeSlug = Str::slug($row['programme_title']);

        $funder = $row['funder_name'] !== '' ? Funder::firstOrCreate(['slug' => Str::slug($row['funder_name'])], ['name' => $row['funder_name']]) : null;
        $programme = ScholarshipProgramme::firstOrCreate(['slug' => $programmeSlug], [
            'title' => $row['programme_title'], 'funder_id' => $funder?->id, 'created_by' => $by?->id,
            'host_country_id' => $row['host_country_code'] !== '' ? $maps['countries'][strtoupper($row['host_country_code'])] : null,
        ]);

        $cycle = $this->findCycle($key, $row);
        $fields = [
            'intake_year' => (int) $row['cycle_year'], 'intake_term' => $row['intake_term'] ?: null, 'funding_type' => $row['funding_type'],
            'funding_summary' => $row['funding_summary'] ?: null, 'teaching_language' => $row['teaching_language'] ?: null,
            'delivery_mode' => $row['delivery_mode'] ?: 'unknown', 'summary' => $row['summary'] ?: null,
            'any_nationality' => strtoupper(trim($row['nationality_codes'])) === 'ANY',
        ];
        if ($cycle) {
            if (! in_array($cycle->publication_state, [PublicationState::Draft, PublicationState::ChangesRequested], true)) {
                throw new \RuntimeException('cycle is no longer editable by import');
            }
            $cycle->update($fields); // fully_funded_verified is never set by import: it needs editorial verification
        } else {
            $cycle = new ScholarshipCycle($fields);
            $cycle->programme_id = $programme->id;
            $cycle->cycle_key = (string) (int) $row['cycle_year'].($row['intake_term'] !== '' ? '-'.Str::slug($row['intake_term']) : '');
            $cycle->slug = $cycle->cycle_key;
            $cycle->external_key = $key;
            $cycle->created_by = $by?->id;
            $cycle->publication_state = PublicationState::Draft; // never Published from an import
            $cycle->save();
        }

        $cycle->degreeLevels()->sync($this->ids($row['degree_codes'], $maps['degrees']));
        $cycle->destinations()->sync($this->ids($row['destination_codes'], $maps['countries'], true));
        $cycle->subjects()->sync($this->ids($row['subject_slugs'], $maps['subjects']));
        $cycle->nationalities()->sync(strtoupper(trim($row['nationality_codes'])) === 'ANY' ? [] : $this->ids($row['nationality_codes'], $maps['countries'], true));

        $route = null;
        if ($row['route_name'] !== '' || $row['application_url'] !== '') {
            $route = ScholarshipRoute::updateOrCreate(['cycle_id' => $cycle->id, 'name' => $row['route_name'] ?: 'Official route'],
                ['route_type' => 'funder_portal', 'application_url' => $row['application_url'] ?: null]);
        }

        $evidence = SourceEvidence::firstOrNew(['cycle_id' => $cycle->id, 'url' => $row['source_url']]);
        $evidence->fill(['publisher' => $row['source_publisher'], 'publisher_type' => $row['source_publisher_type'], 'checked_at' => CarbonImmutable::parse($row['source_checked_on']),
            'note' => 'Imported from CSV (import evidence is a lead until a reviewer verifies it)']);
        $evidence->created_by ??= $by?->id;
        $evidence->save();

        $this->applyDeadline($cycle, $row, $route?->id, $by);
        app(SearchIndexer::class)->reindex($cycle->fresh());
    }

    private function applyDeadline(ScholarshipCycle $cycle, array $row, ?int $routeId, ?User $by): void
    {
        $input = [
            'date_type' => $row['deadline_type'], 'local_closes_at' => $row['deadline_local_datetime'] ?: null, 'source_timezone' => $row['deadline_timezone'] ?: null,
            'closes_on' => $row['deadline_date'] ?: null, 'source_text' => $row['deadline_source_text'] ?: null,
        ];
        $action = app(ChangeDeadline::class);
        $attrs = $action->normalise($input);
        $current = CycleDeadline::where('cycle_id', $cycle->id)->whereNull('superseded_at')->where('route_id', $routeId)->first();

        // Replays with identical data must not create a new deadline version.
        if ($current
            && $current->date_type->value === $attrs['date_type']
            && optional($current->closes_at_utc)->toIso8601String() === ($attrs['closes_at_utc']?->toIso8601String())
            && optional($current->closes_on)->toDateString() === $attrs['closes_on']
            && $current->source_timezone === $attrs['source_timezone']) {
            return;
        }
        $action($cycle, $input, $by, 'CSV import', $routeId);
    }

    // ------------------------------------------------------------- validation

    /** @return list<string> */
    public function validateRow(array $r, array $maps): array
    {
        $e = [];
        $req = fn (string $f, string $label) => $r[$f] === '' ? $e[] = "{$label} is required." : null;

        $req('programme_title', 'programme_title');
        $req('source_url', 'source_url');
        $req('source_publisher', 'source_publisher');
        if (mb_strlen($r['programme_title']) > 190) {
            $e[] = 'programme_title is longer than 190 characters.';
        }
        if (! preg_match('/^\d{4}$/', $r['cycle_year']) || (int) $r['cycle_year'] < 2020 || (int) $r['cycle_year'] > 2060) {
            $e[] = 'cycle_year must be a 4-digit year between 2020 and 2060.';
        }
        if (! in_array($r['funding_type'], ['full', 'partial', 'tuition_only', 'stipend_only', 'other', 'unknown'], true)) {
            $e[] = "funding_type '{$r['funding_type']}' is not one of full, partial, tuition_only, stipend_only, other, unknown.";
        }
        if ($r['delivery_mode'] !== '' && ! in_array($r['delivery_mode'], ['on_campus', 'online', 'hybrid', 'unknown'], true)) {
            $e[] = "delivery_mode '{$r['delivery_mode']}' is invalid.";
        }
        if ($r['source_url'] !== '' && ! $this->safeUrl($r['source_url'])) {
            $e[] = 'source_url must be a public http(s) address.';
        }
        if ($r['application_url'] !== '' && ! $this->safeUrl($r['application_url'])) {
            $e[] = 'application_url must be a public http(s) address.';
        }
        if (! in_array($r['source_publisher_type'], ['funder', 'government', 'embassy', 'university', 'aggregator'], true)) {
            $e[] = "source_publisher_type '{$r['source_publisher_type']}' must be funder, government, embassy, university or aggregator.";
        }
        if (! $this->isoDate($r['source_checked_on'])) {
            $e[] = 'source_checked_on must be an ISO date (YYYY-MM-DD).';
        } elseif ($r['source_checked_on'] > now()->toDateString()) {
            $e[] = 'source_checked_on cannot be in the future.';
        }
        if ($r['host_country_code'] !== '' && ! isset($maps['countries'][strtoupper($r['host_country_code'])])) {
            $e[] = "host_country_code '{$r['host_country_code']}' is not a known ISO country code.";
        }
        foreach ($this->split($r['degree_codes']) as $c) {
            isset($maps['degrees'][$c]) || $e[] = "Unknown degree code '{$c}'.";
        }
        foreach ($this->split($r['destination_codes']) as $c) {
            isset($maps['countries'][strtoupper($c)]) || $e[] = "Unknown destination code '{$c}'.";
        }
        if (strtoupper(trim($r['nationality_codes'])) !== 'ANY') {
            foreach ($this->split($r['nationality_codes']) as $c) {
                isset($maps['countries'][strtoupper($c)]) || $e[] = "Unknown nationality code '{$c}' (use ISO codes or ANY).";
            }
        }
        foreach ($this->split($r['subject_slugs']) as $c) {
            isset($maps['subjects'][$c]) || $e[] = "Unknown subject '{$c}'.";
        }

        $type = DeadlineType::tryFrom($r['deadline_type']);
        if (! $type) {
            $e[] = "deadline_type '{$r['deadline_type']}' must be exact, date_only, rolling, not_announced or route_dependent.";
        } elseif ($type === DeadlineType::Exact) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $r['deadline_local_datetime']) || strtotime($r['deadline_local_datetime']) === false) {
                $e[] = 'An exact deadline needs deadline_local_datetime as YYYY-MM-DD HH:MM.';
            }
            if (! in_array($r['deadline_timezone'], \DateTimeZone::listIdentifiers(), true)) {
                $e[] = 'An exact deadline needs deadline_timezone as an IANA zone (e.g. Asia/Shanghai).';
            }
        } elseif ($type === DeadlineType::DateOnly) {
            $this->isoDate($r['deadline_date']) || $e[] = 'A date_only deadline needs deadline_date as YYYY-MM-DD. No time is assumed.';
            $r['deadline_local_datetime'] === '' || $e[] = 'A date_only deadline must not carry a time: leave deadline_local_datetime empty.';
        }

        return $e;
    }

    // ----------------------------------------------------------------- helpers

    private function identity(array $r): string
    {
        return $r['external_key'] !== '' ? $r['external_key'] : Str::slug($r['programme_title']).'|'.(int) $r['cycle_year'].($r['intake_term'] !== '' ? '|'.Str::slug($r['intake_term']) : '');
    }

    private function findCycle(string $key, array $row): ?ScholarshipCycle
    {
        $byKey = ScholarshipCycle::where('external_key', $key)->first();
        if ($byKey) {
            return $byKey;
        }
        $programme = ScholarshipProgramme::where('slug', Str::slug($row['programme_title']))->first();
        if (! $programme) {
            return null;
        }
        $cycleKey = (string) (int) $row['cycle_year'].($row['intake_term'] !== '' ? '-'.Str::slug($row['intake_term']) : '');

        return ScholarshipCycle::where('programme_id', $programme->id)->where('cycle_key', $cycleKey)->first();
    }

    private function lookups(): array
    {
        return [
            'countries' => Country::pluck('id', 'code')->all(),
            'degrees' => DegreeLevel::pluck('id', 'code')->all(),
            'subjects' => Subject::pluck('id', 'slug')->all(),
        ];
    }

    private function ids(string $csv, array $map, bool $upper = false): array
    {
        return collect($this->split($csv))->map(fn ($c) => $map[$upper ? strtoupper($c) : $c] ?? null)->filter()->values()->all();
    }

    /** @return list<string> */
    private function split(string $v): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $v)), fn ($x) => $x !== ''));
    }

    private function isoDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4));
    }

    /** Public http(s) only. Never accepts internal hosts, IP literals in private ranges or other schemes (SEC-04). */
    public function safeUrl(string $url): bool
    {
        if (strlen($url) > 2000 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user'])) {
            return false;
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || ! str_contains($host, '.') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    private function fail(Import $import, string $message): Import
    {
        $import->update(['status' => 'failed', 'summary' => ['error' => $message]]);

        return $import;
    }

    // -------------------------------------------------------------- csv export

    /** Error file for download. Cells that could be executed as formulas are neutralised (IMP-02). */
    public static function errorCsv(Import $import): string
    {
        $out = self::csvLine(['row_number', 'errors', ...self::COLUMNS]);
        foreach ($import->rows()->where('status', 'invalid')->orderBy('row_number')->get() as $r) {
            $out .= self::csvLine([$r->row_number, implode(' | ', $r->errors ?? []), ...array_map(fn ($c) => $r->payload[$c] ?? '', self::COLUMNS)]);
        }

        return $out;
    }

    public static function neutralise(string $cell): string
    {
        return preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }

    public static function csvLine(array $cells): string
    {
        return implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', self::neutralise((string) $c)).'"', $cells))."\r\n";
    }
}
