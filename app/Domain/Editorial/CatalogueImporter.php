<?php

namespace App\Domain\Editorial;

use App\Models\Country;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\University;
use App\Models\UniversityProgramme;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff CSV import of universities and of their degree programmes: stage -> validate -> preview -> commit, like the scholarship import.
 * - Nothing goes live: a new university or programme arrives hidden (published = false) and staff publish it themselves.
 * - Never overwrites: for something that already exists, an import only fills fields that are empty, and never changes whether it is public.
 * - Idempotent: a university is identified by its name (slug), a programme by university + level + name + teaching language.
 * - Contains no student data and fetches nothing from the network.
 */
class CatalogueImporter
{
    public const MAX_ROWS = 5000;

    public const UNIVERSITY_COLUMNS = ['name', 'name_zh', 'country_code', 'province', 'city', 'website', 'summary', 'source_url', 'source_checked_on', 'csc_key'];

    public const PROGRAMME_COLUMNS = ['university', 'level', 'name_zh', 'name_en', 'teaching_language', 'duration_years', 'tuition_cny', 'csc_eligible'];

    public static function columnsFor(string $kind): array
    {
        return $kind === 'programmes' ? self::PROGRAMME_COLUMNS : self::UNIVERSITY_COLUMNS;
    }

    // ------------------------------------------------------------------ stage

    /** Parses and validates every row; nothing is written to the catalogue. */
    public function stage(string $path, string $filename, string $kind, ?User $by = null): Import
    {
        $columns = self::columnsFor($kind);
        $import = Import::create(['user_id' => $by?->id, 'kind' => $kind, 'filename' => basename($filename), 'schema_version' => '1', 'status' => 'staged']);

        $handle = fopen($path, 'r');
        if (! $handle) {
            return $this->fail($import, 'The file could not be read.');
        }
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);

            return $this->fail($import, 'The file is empty.');
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        if ($missing = array_diff($columns, $header)) {
            fclose($handle);

            return $this->fail($import, 'Missing columns: '.implode(', ', $missing).'. Download the template and keep its header row.');
        }

        $maps = ['countries' => Country::pluck('id', 'code')->all()];
        $seen = [];
        $rowNumber = 1;
        $valid = $errors = $dups = 0;

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($rowNumber - 1 > self::MAX_ROWS) {
                fclose($handle);

                return $this->fail($import, 'Too many rows (max '.self::MAX_ROWS.'). Split the file.');
            }
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            if (count($cells) !== count($header)) {
                ImportRow::create(['import_id' => $import->id, 'row_number' => $rowNumber, 'payload' => [], 'status' => 'invalid',
                    'errors' => ['Row has '.count($cells).' columns, expected '.count($header).' (check quoting and commas).']]);
                $errors++;

                continue;
            }
            $row = array_intersect_key(array_map(fn ($v) => trim((string) $v), array_combine($header, $cells)), array_flip($columns));
            $key = $this->identity($kind, $row);
            $problems = $kind === 'programmes' ? $this->validateProgramme($row) : $this->validateUniversity($row, $maps);
            if (isset($seen[$key])) {
                $problems[] = "Duplicate of row {$seen[$key]} in this file.";
                $dups++;
            }
            $seen[$key] ??= $rowNumber;
            $existing = $kind === 'programmes' ? $this->findProgramme($row) : $this->findUniversity($row);

            ImportRow::create([
                'import_id' => $import->id, 'row_number' => $rowNumber, 'external_key' => Str::limit($key, 125, ''), 'payload' => $row,
                'status' => $problems ? 'invalid' : 'valid', 'action' => $problems ? null : ($existing ? 'update' : 'create'), 'errors' => $problems ?: null,
            ]);
            $problems ? $errors++ : $valid++;
        }
        fclose($handle);

        $import->update([
            'status' => 'validated', 'total_rows' => $valid + $errors, 'valid_rows' => $valid, 'error_rows' => $errors,
            'summary' => ['creates' => $import->rows()->where('action', 'create')->count(), 'updates' => $import->rows()->where('action', 'update')->count(), 'duplicates_in_file' => $dups],
        ]);
        AuditLog::record('import.staged', $import, null, ['kind' => $kind, 'rows' => $valid + $errors, 'errors' => $errors], null, $by);

        return $import;
    }

    // ----------------------------------------------------------------- commit

    /** Applies the valid, not-yet-applied rows. Safe to run repeatedly. */
    public function commit(Import $import, ?User $by = null): Import
    {
        if (! in_array($import->status, ['validated', 'committed'], true)) {
            return $import;
        }
        $countries = Country::pluck('id', 'code')->all();

        $import->rows()->where('status', 'valid')->where('applied', false)->orderBy('row_number')->chunkById(200, function ($rows) use ($import, $countries) {
            foreach ($rows as $r) {
                try {
                    DB::transaction(function () use ($r, $import, $countries) {
                        $import->kind === 'programmes' ? $this->applyProgramme($r->payload) : $this->applyUniversity($r->payload, $countries);
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
        AuditLog::record('import.committed', $import, null, ['kind' => $import->kind, 'applied' => $import->rows()->where('applied', true)->count()], null, $by);

        return $import->fresh();
    }

    private function applyUniversity(array $r, array $countries): void
    {
        $values = [
            'name_zh' => $r['name_zh'] ?: null, 'country_id' => $countries[strtoupper($r['country_code'])] ?? null, 'province' => $r['province'] ?: null,
            'city' => $r['city'] ?: null, 'website' => $r['website'] ?: null, 'domain' => $r['website'] ? $this->domain($r['website']) : null,
            'summary' => $r['summary'] ?: null, 'source_url' => $r['source_url'], 'catalogue_checked_at' => CarbonImmutable::parse($r['source_checked_on']),
            'csc_key' => $r['csc_key'] ?: null,
        ];
        $university = $this->findUniversity($r);
        if ($university) {
            // Fill the gaps only: what is already there was entered or checked by someone, and whether it is public stays as it is.
            $university->fill(array_filter($values, fn ($v, $k) => $v !== null && blank($university->{$k}), ARRAY_FILTER_USE_BOTH))->save();

            return;
        }
        $university = new University($values);
        $university->forceFill(['name' => $r['name'], 'slug' => Str::slug($r['name']), 'published' => false])->save();
    }

    private function applyProgramme(array $r): void
    {
        $university = $this->universityOf($r);
        $values = [
            'name_en' => $r['name_en'] ?: null, 'duration_years' => $r['duration_years'] !== '' ? (float) $r['duration_years'] : null,
            'tuition_cny' => $r['tuition_cny'] !== '' ? (int) $r['tuition_cny'] : null,
        ];
        $programme = $this->findProgramme($r);
        if ($programme) {
            $programme->fill(array_filter($values, fn ($v, $k) => $v !== null && blank($programme->{$k}), ARRAY_FILTER_USE_BOTH));
            $programme->csc_eligible = $programme->csc_eligible || $this->yes($r['csc_eligible']);
            $programme->save();

            return;
        }
        $programme = new UniversityProgramme($values);
        $programme->forceFill([
            'university_id' => $university->id, 'level' => $r['level'], 'name_zh' => $r['name_zh'] ?: $r['name_en'], 'teaching_language' => $r['teaching_language'] ?: null,
            'csc_eligible' => $this->yes($r['csc_eligible']), 'published' => false, 'name_en_source' => $r['name_en'] ? 'staff' : null,
        ])->save();
    }

    // ------------------------------------------------------------- validation

    /** @return list<string> */
    public function validateUniversity(array $r, array $maps): array
    {
        $e = [];
        $r['name'] === '' ? $e[] = 'name is required.' : (mb_strlen($r['name']) > 190 ? $e[] = 'name is longer than 190 characters.' : null);
        if ($r['name'] !== '' && Str::slug($r['name']) === '') {
            $e[] = 'name needs letters or digits in the Latin alphabet (write the English name; put Chinese characters in name_zh).';
        }
        if ($r['country_code'] === '' || ! isset($maps['countries'][strtoupper($r['country_code'])])) {
            $e[] = $r['country_code'] === '' ? 'country_code is required.' : "country_code '{$r['country_code']}' is not a known ISO country code.";
        }
        if (mb_strlen($r['name_zh']) > 255 || mb_strlen($r['province']) > 64 || mb_strlen($r['city']) > 255) {
            $e[] = 'name_zh, province or city is too long.';
        }
        if (mb_strlen($r['summary']) > 2000) {
            $e[] = 'summary is longer than 2000 characters.';
        }
        if ($r['website'] !== '' && ! (new ScholarshipImporter)->safeUrl($r['website'])) {
            $e[] = 'website must be a public http(s) address.';
        }
        if ($r['source_url'] === '') {
            $e[] = 'source_url is required: give the official page the information comes from.';
        } elseif (! (new ScholarshipImporter)->safeUrl($r['source_url'])) {
            $e[] = 'source_url must be a public http(s) address.';
        }
        if (! $this->isoDate($r['source_checked_on'])) {
            $e[] = 'source_checked_on must be an ISO date (YYYY-MM-DD).';
        } elseif ($r['source_checked_on'] > now()->toDateString()) {
            $e[] = 'source_checked_on cannot be in the future.';
        }
        if ($r['csc_key'] !== '') {
            if (mb_strlen($r['csc_key']) > 32) {
                $e[] = 'csc_key is longer than 32 characters.';
            } elseif (University::where('csc_key', $r['csc_key'])->where('slug', '!=', Str::slug($r['name']))->exists()) {
                $e[] = "csc_key '{$r['csc_key']}' already belongs to another university.";
            }
        }

        return $e;
    }

    /** @return list<string> */
    public function validateProgramme(array $r): array
    {
        $e = [];
        if ($r['university'] === '') {
            $e[] = 'university is required.';
        } elseif (! $this->universityOf($r)) {
            $e[] = "university '{$r['university']}' does not exist yet. Import the universities first and spell the name the same way.";
        }
        if (! array_key_exists($r['level'], UniversityProgramme::LEVELS)) {
            $e[] = "level '{$r['level']}' must be one of ".implode(', ', array_keys(UniversityProgramme::LEVELS)).'.';
        }
        if ($r['name_zh'] === '' && $r['name_en'] === '') {
            $e[] = 'Fill name_zh or name_en.';
        }
        if (mb_strlen($r['name_zh']) > 255 || mb_strlen($r['name_en']) > 255) {
            $e[] = 'A programme name is longer than 255 characters.';
        }
        if (mb_strlen($r['teaching_language']) > 48) {
            $e[] = 'teaching_language is longer than 48 characters.';
        }
        if ($r['duration_years'] !== '' && (! is_numeric($r['duration_years']) || (float) $r['duration_years'] < 0.5 || (float) $r['duration_years'] > 12)) {
            $e[] = 'duration_years must be a number of years between 0.5 and 12.';
        }
        if ($r['tuition_cny'] !== '' && (! preg_match('/^\d{1,8}$/', $r['tuition_cny']))) {
            $e[] = 'tuition_cny must be a whole number (yuan per year) without symbols or separators.';
        }
        if (! in_array(strtolower($r['csc_eligible']), ['', 'yes', 'no', 'true', 'false', '1', '0'], true)) {
            $e[] = 'csc_eligible must be yes or no.';
        }

        return $e;
    }

    // ----------------------------------------------------------------- helpers

    private function identity(string $kind, array $r): string
    {
        return $kind === 'programmes'
            ? Str::slug($r['university']).'|'.$r['level'].'|'.mb_strtolower($r['name_zh'] ?: $r['name_en']).'|'.mb_strtolower($r['teaching_language'])
            : Str::slug($r['name']);
    }

    private function findUniversity(array $r): ?University
    {
        $slug = Str::slug($r['name']);

        return $slug === '' ? null : University::where('slug', $slug)->first();
    }

    private function universityOf(array $r): ?University
    {
        $slug = Str::slug($r['university']);

        return $slug === '' ? null : University::where('slug', $slug)->first();
    }

    private function findProgramme(array $r): ?UniversityProgramme
    {
        $university = $this->universityOf($r);
        if (! $university) {
            return null;
        }

        return UniversityProgramme::where('university_id', $university->id)->where('level', $r['level'])
            ->where('name_zh', $r['name_zh'] ?: $r['name_en'])->where('teaching_language', $r['teaching_language'] ?: null)->first();
    }

    private function yes(string $v): bool
    {
        return in_array(strtolower($v), ['yes', 'true', '1'], true);
    }

    private function domain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./i', '', strtolower($host)) : null;
    }

    private function isoDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4));
    }

    private function fail(Import $import, string $message): Import
    {
        $import->update(['status' => 'failed', 'summary' => ['error' => $message]]);

        return $import;
    }

    /** Error file for download, with formula-looking cells neutralised. */
    public static function errorCsv(Import $import): string
    {
        $columns = self::columnsFor($import->kind);
        $out = ScholarshipImporter::csvLine(['row_number', 'errors', ...$columns]);
        foreach ($import->rows()->where('status', 'invalid')->orderBy('row_number')->get() as $r) {
            $out .= ScholarshipImporter::csvLine([$r->row_number, implode(' | ', $r->errors ?? []), ...array_map(fn ($c) => $r->payload[$c] ?? '', $columns)]);
        }

        return $out;
    }
}
