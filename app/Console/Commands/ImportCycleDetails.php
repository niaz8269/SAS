<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\CycleMilestone;
use App\Models\ScholarshipCycle;
use App\Models\University;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the full timeline (opening, interviews, tests, results, other dates), the participating universities and the programmes
 * offered to cycles, from researched JSON files. New scholarships in the same files are first created as drafts through the
 * normal CSV importer. Dates are stored exactly as the source supports them; anything not announced stays "not announced".
 */
class ImportCycleDetails extends Command
{
    protected $signature = 'sas:import-cycle-details {files* : JSON files (arrays of entries)} {--dry-run : validate only}';

    protected $description = 'Import researched timelines, universities and programmes into scholarship cycles; create new scholarships as drafts.';

    private const BASE_COLUMNS = ['external_key', 'programme_title', 'funder_name', 'host_country_code', 'cycle_year', 'intake_term', 'funding_type', 'funding_summary',
        'teaching_language', 'delivery_mode', 'degree_codes', 'destination_codes', 'nationality_codes', 'subject_slugs', 'summary',
        'source_url', 'source_publisher', 'source_publisher_type', 'source_checked_on', 'route_name', 'application_url',
        'deadline_type', 'deadline_local_datetime', 'deadline_timezone', 'deadline_date', 'deadline_source_text'];

    public function handle(): int
    {
        $entries = [];
        foreach ($this->argument('files') as $file) {
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (! is_array($data)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            foreach ($data as $e) {
                $entries[$e['external_key']] = $e;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $new = array_filter($entries, fn ($e) => ! empty($e['is_new']) && ! ScholarshipCycle::where('external_key', $e['external_key'])->exists());
        if ($new) {
            $csv = tempnam(sys_get_temp_dir(), 'cyc').'.csv';
            $fh = fopen($csv, 'w');
            fputcsv($fh, self::BASE_COLUMNS);
            foreach ($new as $key => $e) {
                $row = $this->csvRow($key, $e);
                fputcsv($fh, array_map(fn ($c) => $row[$c] ?? '', self::BASE_COLUMNS));
            }
            fclose($fh);
            $this->call('sas:import-scholarships', ['file' => $csv] + ($dry ? [] : ['--commit' => true]));
            unlink($csv);
        }
        if ($dry) {
            $this->info(count($entries).' entries read; '.count($new).' new.');

            return self::SUCCESS;
        }

        $stats = ['cycles' => 0, 'milestones' => 0, 'universities_linked' => 0, 'missing' => []];
        foreach ($entries as $key => $e) {
            $cycle = ScholarshipCycle::where('external_key', $key)->first();
            if (! $cycle) {
                $stats['missing'][] = $key;

                continue;
            }
            DB::transaction(function () use ($cycle, $e, &$stats) {
                $cycle->milestones()->delete();
                foreach (array_values($e['milestones'] ?? []) as $i => $m) {
                    $kind = in_array($m['kind'] ?? '', array_keys(CycleMilestone::KINDS), true) ? $m['kind'] : 'other';
                    $type = in_array($m['date_type'] ?? '', array_keys(CycleMilestone::DATE_TYPES), true) ? $m['date_type'] : 'not_announced';
                    $from = ! empty($m['starts_on']) ? $m['starts_on'] : null;
                    if (in_array($type, ['exact', 'date_only', 'month', 'range'], true) && ! $from) {
                        $type = 'not_announced'; // a dated type without a date is shown honestly as not announced
                    }
                    CycleMilestone::create(['cycle_id' => $cycle->id, 'kind' => $kind, 'label' => $m['label'] ?? null, 'date_type' => $type, 'starts_on' => $from,
                        'ends_on' => $m['ends_on'] ?? null, 'time_text' => $m['time_text'] ?? null, 'source_text' => isset($m['source_text']) ? Str::limit($m['source_text'], 590, '') : null,
                        'source_url' => $m['source_url'] ?? null, 'sort' => ($i + 1) * 10]);
                    $stats['milestones']++;
                }

                $inst = $e['institutions'] ?? [];
                $update = [];
                if (array_key_exists('note', $inst)) {
                    $update['institutions_note'] = $inst['note'] ?: null;
                }
                if (array_key_exists('programmes_note', $e)) {
                    $update['programmes_note'] = $e['programmes_note'] ?: null;
                }
                if ($update) {
                    $cycle->forceFill($update)->save();
                }

                $ids = [];
                foreach ($inst['names'] ?? [] as $name) {
                    $uni = $this->university((string) $name, $inst['country_code'] ?? null);
                    if ($uni) {
                        $ids[] = $uni->id;
                    }
                }
                if ($ids) {
                    $cycle->universities()->syncWithoutDetaching($ids);
                    $stats['universities_linked'] += count($ids);
                }
                $stats['cycles']++;
            });
        }

        $this->info("Updated {$stats['cycles']} cycle(s): {$stats['milestones']} milestone(s), {$stats['universities_linked']} university link(s).");
        if ($stats['missing']) {
            $this->warn('No cycle for: '.implode(', ', $stats['missing']));
        }

        return self::SUCCESS;
    }

    private function csvRow(string $key, array $e): array
    {
        $b = $e['base'] ?? [];
        $d = $e['deadline'] ?? [];
        $join = fn ($v) => is_array($v) ? implode('|', $v) : (string) $v;

        return [
            'external_key' => $key, 'programme_title' => $b['programme_title'] ?? '', 'funder_name' => $b['funder_name'] ?? '', 'host_country_code' => $b['host_country_code'] ?? '',
            'cycle_year' => $b['cycle_year'] ?? '', 'intake_term' => $b['intake_term'] ?? '', 'funding_type' => $b['funding_type'] ?? 'unknown', 'funding_summary' => $b['funding_summary'] ?? '',
            'teaching_language' => $b['teaching_language'] ?? '', 'delivery_mode' => 'on_campus', 'degree_codes' => $join($b['degree_codes'] ?? []),
            'destination_codes' => $join($b['destination_codes'] ?? ($b['host_country_code'] ?? '')), 'nationality_codes' => $join($b['nationality_codes'] ?? []), 'subject_slugs' => '',
            'summary' => $b['summary'] ?? '', 'source_url' => $b['source_url'] ?? '', 'source_publisher' => $b['source_publisher'] ?? '', 'source_publisher_type' => $b['source_publisher_type'] ?? 'government',
            'source_checked_on' => $e['checked_on'] ?? now()->toDateString(), 'route_name' => $b['route_name'] ?? 'Application route', 'application_url' => '',
            'deadline_type' => $d['type'] ?? 'not_announced', 'deadline_local_datetime' => $d['local_datetime'] ?? '', 'deadline_timezone' => $d['timezone'] ?? '', 'deadline_date' => $d['date'] ?? '',
            'deadline_source_text' => $d['source_text'] ?? '',
        ];
    }

    private function university(string $name, ?string $countryCode): ?University
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $found = University::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($found) {
            return $found;
        }
        $country = $countryCode ? Country::where('code', strtoupper($countryCode))->first() : null;
        $slug = Str::slug($name) ?: 'university';
        for ($i = 2; University::where('slug', $slug)->exists(); $i++) {
            $slug = Str::slug($name).'-'.$i;
        }

        return University::create(['name' => $name, 'slug' => $slug, 'country_id' => $country?->id]);
    }
}
