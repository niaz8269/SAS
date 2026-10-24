<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Domain\Editorial\ScholarshipImporter;
use App\Models\Country;
use App\Models\University;
use App\Models\UniversityFeeStructure;
use App\Models\UniversityScholarship;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Loads researched university details for medical programmes: the profile (founding year, hospitals, recognitions), the fees the
 * university or the government charges, and the scholarships open to international students. A university is shown publicly only when
 * it has a published fee structure AND a published scholarship, so a university without a scholarship stays hidden.
 *
 * Rows are published only when the entry says "published": true (used for fee sheets the owner gave to SAS). Everything else arrives hidden
 * and is switched on in the admin after review. A recognition counts as "verified" only with a source link.
 */
class ImportUniversityDetails extends Command
{
    protected $signature = 'sas:import-university-details {files* : JSON files (arrays of universities)} {--report= : write a Markdown report to this path} {--dry-run : report only, change nothing}';

    protected $description = 'Import university profiles, medical fee structures and scholarships for international students.';

    private ScholarshipImporter $urls;

    public function handle(): int
    {
        $this->urls = new ScholarshipImporter;
        $entries = [];
        foreach ($this->argument('files') as $file) {
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (! is_array($data)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            foreach ($data as $e) {
                if (! empty($e['university']['name'])) {
                    $entries[Str::slug($e['university']['name']) ?: $e['university']['name']] = $e;
                }
            }
        }
        $dry = (bool) $this->option('dry-run');
        $countries = Country::pluck('id', 'code')->all();
        $report = [];
        $totals = ['universities' => 0, 'created' => 0, 'fees' => 0, 'scholarships' => 0, 'listed' => 0, 'hidden' => 0];

        foreach ($entries as $slug => $e) {
            $lines = [];
            $u = $e['university'];
            $code = strtoupper((string) ($u['country_code'] ?? 'CN'));
            // The same university may be named with a school in brackets ("Sichuan University (West China School of Medicine)"): that is not a second university.
            $plainSlug = Str::slug(preg_replace('/\s*\([^)]*\)/', '', $u['name']) ?? '');
            $existing = University::where('slug', $slug)->first() ?? ($plainSlug !== '' ? University::where('slug', $plainSlug)->first() : null)
                ?? (! empty($u['name_zh']) ? University::where('name_zh', $u['name_zh'])->first() : null);
            $fields = array_filter([
                'name_zh' => $u['name_zh'] ?? null, 'province' => $u['province'] ?? null, 'city' => $u['city'] ?? null,
                'website' => ! empty($u['website']) && $this->urls->safeUrl($u['website']) ? $u['website'] : null, 'country_id' => $countries[$code] ?? null,
            ], fn ($v) => filled($v));
            if (! $dry) {
                if ($existing) {
                    $existing->fill(array_filter($fields, fn ($v, $k) => blank($existing->{$k}), ARRAY_FILTER_USE_BOTH))->save(); // never overwrite what is already there
                    $university = $existing;
                } else {
                    $university = new University($fields);
                    $university->forceFill(['name' => trim($u['name']), 'slug' => $slug, 'published' => true])->save();
                    $totals['created']++;
                }
                $profile = $this->profile($e['profile'] ?? [], $university->profile ?? []);
                $university->forceFill(['profile' => $profile, 'profile_checked_at' => now()->toDateString()])->save();
            } else {
                $university = $existing ?? new University(['name' => $u['name']]);
            }
            $totals['universities']++;
            $lines[] = ($existing ? 'Existing university updated' : 'New university added').': '.$university->name;

            $feeCount = $scholarshipCount = 0;
            foreach ($e['fee_structures'] ?? [] as $i => $f) {
                $row = $this->feeStructure($f);
                if (! $row) {
                    $lines[] = 'A fee structure was skipped (needs a programme name).';

                    continue;
                }
                if (! $dry) {
                    $model = UniversityFeeStructure::firstOrNew(['university_id' => $university->id, 'programme_name' => $row['programme_name'], 'intake_label' => $row['intake_label']]);
                    $model->forceFill($row + ['sort' => $i * 10])->save();
                }
                $feeCount++;
                $lines[] = "Fees: {$row['programme_name']}, {$row['intake_label']} (".($row['published'] ? 'published' : 'hidden until reviewed').')';
            }
            foreach ($e['scholarships'] ?? [] as $i => $s) {
                if (blank($s['name'] ?? null)) {
                    continue;
                }
                $source = $this->source($s['source_url'] ?? null, $s['source_type'] ?? 'university');
                $row = [
                    'provider' => array_key_exists($s['provider'] ?? '', UniversityScholarship::PROVIDERS) ? $s['provider'] : 'university',
                    'coverage' => $this->text($s['coverage'] ?? null, 500), 'amount_text' => $this->text($s['amount_text'] ?? null, 300), 'eligibility' => $this->text($s['eligibility'] ?? null, 1500),
                    'source_type' => $source['type'], 'source_label' => $this->text($s['source_label'] ?? null, 190), 'source_url' => $source['url'],
                    'source_text' => $this->text($s['source_text'] ?? null, 600), 'checked_on' => $this->date($s['checked_on'] ?? null) ?? now()->toDateString(),
                    'published' => (bool) ($s['published'] ?? false), 'sort' => $i * 10,
                ];
                if ($source['type'] !== 'supplied' && ! $source['url']) {
                    $row['published'] = false; // a scholarship without an official page is never shown
                }
                if (! $dry) {
                    UniversityScholarship::updateOrCreate(['university_id' => $university->id, 'name' => Str::limit(trim($s['name']), 190, '')], $row);
                }
                $scholarshipCount++;
                $lines[] = "Scholarship: {$s['name']} (".($row['published'] ? 'published' : 'hidden until reviewed').')';
            }
            // What the university itself publishes replaces what an agency sheet said: the sheet's rows (and its scholarships) are removed
            // so that a page never shows two different fee tables for the same programme.
            if (! $dry && $university->exists) {
                collect($e['fee_structures'] ?? [])->contains(fn ($f) => ($f['source_type'] ?? 'university') !== 'supplied') && UniversityFeeStructure::where('university_id', $university->id)->where('source_type', 'supplied')->delete();
                collect($e['scholarships'] ?? [])->contains(fn ($s) => ($s['source_type'] ?? 'university') !== 'supplied' && filled($s['name'] ?? null)) && UniversityScholarship::where('university_id', $university->id)->where('source_type', 'supplied')->delete();
            }
            $totals['fees'] += $feeCount;
            $totals['scholarships'] += $scholarshipCount;
            $scholarshipCount === 0 && $lines[] = 'No scholarship found: this university is not listed.';
            foreach ($e['notes'] ?? [] as $n) {
                $lines[] = "Note: {$n}";
            }
            $listed = ! $dry && $university->exists && $university->isMedicalListed();
            $listed ? $totals['listed']++ : $totals['hidden']++;
            ! $dry && AuditLog::record('university.details_imported', $university, null, ['fees' => $feeCount, 'scholarships' => $scholarshipCount], null, null);
            $report[$university->name] = array_merge([$listed ? 'LISTED publicly' : 'Not listed publicly yet'], $lines);
        }

        $this->info(sprintf('%d universities (%d new): %d fee structures, %d scholarships; %d listed now, %d hidden.', $totals['universities'], $totals['created'], $totals['fees'], $totals['scholarships'], $totals['listed'], $totals['hidden']));
        if ($path = $this->option('report')) {
            $md = "# University details\n\n".($dry ? "_Dry run: nothing was changed._\n\n" : '');
            foreach ($report as $name => $lines) {
                $md .= "## {$name}\n\n";
                foreach ($lines as $l) {
                    $md .= "- {$l}\n";
                }
                $md .= "\n";
            }
            file_put_contents($path, $md);
            $this->line("Report written to {$path}");
        }

        return self::SUCCESS;
    }

    private function profile(array $in, array $current): array
    {
        $p = $current;
        foreach (['established_year' => fn ($v) => (is_numeric($v) && $v >= 1000 && $v <= (int) date('Y')) ? (int) $v : null,
            'institution_type' => fn ($v) => $this->text($v, 120), 'summary' => fn ($v) => $this->text($v, 800), 'students_note' => fn ($v) => $this->text($v, 400),
            'affiliated_hospitals_note' => fn ($v) => $this->text($v, 400), 'accommodation_note' => fn ($v) => $this->text($v, 600)] as $key => $clean) {
            if (array_key_exists($key, $in) && ($value = $clean($in[$key])) !== null) {
                $p[$key] = $value;
            }
        }
        if (isset($in['moe_english_mbbs']) && is_bool($in['moe_english_mbbs'])) {
            $p['moe_english_mbbs'] = $in['moe_english_mbbs'];
            $p['moe_source_url'] = ! empty($in['moe_source_url']) && $this->urls->safeUrl($in['moe_source_url']) ? $in['moe_source_url'] : ($p['moe_source_url'] ?? null);
        }
        if (! empty($in['affiliated_hospitals'])) {
            $p['affiliated_hospitals'] = collect($in['affiliated_hospitals'])->filter(fn ($h) => filled($h['name'] ?? null))->take(20)
                ->map(fn ($h) => ['name' => $this->text($h['name'], 190), 'note' => $this->text($h['note'] ?? null, 300)])->values()->all();
        }
        if (! empty($in['recognitions'])) {
            $p['recognitions'] = collect($in['recognitions'])->filter(fn ($r) => filled($r['body'] ?? null))->map(function ($r) {
                $url = ! empty($r['source_url']) && $this->urls->safeUrl($r['source_url']) ? $r['source_url'] : null;

                // Verified needs a link; without one it is only a statement.
                return ['body' => $this->text($r['body'], 190), 'status' => ($r['status'] ?? '') === 'verified' && $url ? 'verified' : 'claimed',
                    'source_label' => $this->text($r['source_label'] ?? null, 190), 'source_url' => $url, 'note' => $this->text($r['note'] ?? null, 300)];
            })->values()->all();
        }
        if (! empty($in['sources'])) {
            $p['sources'] = collect($in['sources'])->filter(fn ($s) => ! empty($s['url']) && $this->urls->safeUrl($s['url']))->map(fn ($s) => ['label' => $this->text($s['label'] ?? $s['url'], 190), 'url' => $s['url']])->values()->all();
        }

        return $p;
    }

    private function feeStructure(array $f): ?array
    {
        if (blank($f['programme_name'] ?? null)) {
            return null;
        }
        $int = fn ($v) => is_numeric($v) && $v >= 0 ? (int) round((float) $v) : null;
        $source = $this->source($f['source_url'] ?? null, $f['source_type'] ?? 'university');
        $fees = collect($f['other_fees'] ?? [])->filter(fn ($x) => filled($x['label'] ?? null) && is_numeric($x['amount'] ?? null))->map(fn ($x) => array_filter([
            'label' => $this->text($x['label'], 190), 'amount' => $int($x['amount']), 'amount_max' => $int($x['amount_max'] ?? null) ?: null,
            'when' => ($x['when'] ?? '') === 'first_year' ? 'first_year' : 'every_year', 'refundable' => ! empty($x['refundable']), 'optional' => ! empty($x['optional']) || (bool) preg_match('/\b(instead of|alternative)\b/i', (string) $x['label']), 'note' => $this->text($x['note'] ?? null, 300),
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();

        return [
            'programme_name' => $this->text($f['programme_name'], 190), 'level' => in_array($f['level'] ?? '', ['bachelor', 'master', 'doctorate'], true) ? $f['level'] : 'bachelor',
            'duration_text' => $this->text($f['duration_text'] ?? null, 190), 'duration_years' => is_numeric($f['duration_years'] ?? null) ? (float) $f['duration_years'] : null,
            'teaching_language' => $this->text($f['teaching_language'] ?? null, 60), 'intake_label' => $this->text($f['intake_label'] ?? null, 80) ?? '',
            'currency' => preg_match('/^[A-Za-z]{3}$/', (string) ($f['currency'] ?? '')) ? strtoupper($f['currency']) : 'CNY',
            'tuition_amount' => $int($f['tuition_amount'] ?? null), 'tuition_amount_later' => $int($f['tuition_amount_later'] ?? null),
            'tuition_period' => ($f['tuition_period'] ?? '') === 'semester' ? 'semester' : 'year', 'tuition_note' => $this->text($f['tuition_note'] ?? null, 300),
            'hostel_amount' => $int($f['hostel_amount'] ?? null), 'hostel_amount_later' => $int($f['hostel_amount_later'] ?? null), 'hostel_note' => $this->text($f['hostel_note'] ?? null, 300),
            'application_fee_amount' => $int($f['application_fee_amount'] ?? null), 'other_fees' => $fees ?: null,
            'hsk_note' => $this->text($f['hsk_note'] ?? null, 400), 'age_note' => $this->text($f['age_note'] ?? null, 200), 'marks_note' => $this->text($f['marks_note'] ?? null, 300),
            'required_documents' => collect($f['required_documents'] ?? [])->map(fn ($d) => $this->text($d, 190))->filter()->values()->all() ?: null,
            'application_deadline' => $this->text($f['application_deadline'] ?? null, 200),
            'source_type' => $source['type'], 'source_label' => $this->text($f['source_label'] ?? null, 190), 'source_url' => $source['url'], 'source_date' => $this->date($f['source_date'] ?? null),
            'notes' => array_values(array_filter(array_map(fn ($n) => $this->text($n, 500), (array) ($f['notes'] ?? [])))) ?: null,
            'published' => (bool) ($f['published'] ?? false),
        ];
    }

    /** @return array{type:string,url:?string} a "supplied" source is a fee sheet given to SAS; anything else needs a public https address. */
    private function source(?string $url, string $type): array
    {
        if ($type === 'supplied') {
            return ['type' => 'supplied', 'url' => null];
        }

        return ['type' => in_array($type, ['university', 'government'], true) ? $type : 'university', 'url' => $url && $this->urls->safeUrl($url) ? $url : null]; // many official university sites are still http only
    }

    private function text(mixed $v, int $max): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');

        return $v === '' ? null : Str::limit($v, $max, '');
    }

    private function date(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && $v <= now()->toDateString() ? CarbonImmutable::parse($v)->toDateString() : null;
    }
}
