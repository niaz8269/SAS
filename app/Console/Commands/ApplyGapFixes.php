<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\CycleMilestone;
use App\Models\ScholarshipCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills gaps in published scholarships from research files, without replacing anything that is already stored:
 * the teaching language only where it is blank or "not stated", the eligible countries only where none are stored,
 * and dates only as new milestones (a date is never added if a milestone of the same kind and label exists).
 * It never changes the funding type, the application status, a confirmed deadline, or whether anything is published.
 * Running it again changes nothing.
 *
 * Entry: {external_key, teaching_language?, any_nationality?, nationality_codes?: [ISO 3166-1 alpha-2], milestones?: [{kind, label, date_type, starts_on?, ends_on?, time_text?, source_text, source_url?}]}
 */
class ApplyGapFixes extends Command
{
    protected $signature = 'sas:apply-gap-fixes {files* : JSON files, one entry or an array of entries} {--dry-run : report only, change nothing}';

    protected $description = 'Fill blank language, eligible countries and dates from research files, without replacing stored values';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $stats = ['cycles' => 0, 'language' => 0, 'any_nationality' => 0, 'nationalities' => 0, 'milestones' => 0, 'unknown' => 0, 'skipped' => 0];

        foreach ((array) $this->argument('files') as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (! is_array($data)) {
                $this->error("Cannot read $file");

                continue;
            }
            $entries = array_is_list($data) ? $data : [$data];
            foreach ($entries as $e) {
                $cycle = ScholarshipCycle::where('external_key', $e['external_key'] ?? '')->first();
                if (! $cycle) {
                    $stats['unknown']++;
                    $this->warn('Unknown key: '.($e['external_key'] ?? '?'));

                    continue;
                }
                $changed = false;
                DB::transaction(function () use ($cycle, $e, $dry, &$stats, &$changed) {
                    $lang = trim((string) ($e['teaching_language'] ?? ''));
                    $current = trim((string) $cycle->teaching_language);
                    if ($lang !== '' && ($current === '' || stripos($current, 'not stated') !== false || stripos($current, 'not confirmed') !== false)) {
                        $changed = true;
                        $stats['language']++;
                        if (! $dry) {
                            $cycle->forceFill(['teaching_language' => mb_substr($lang, 0, 190)])->save();
                        }
                    }

                    if ($cycle->nationalities()->doesntExist()) {
                        $codes = array_values(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), (array) ($e['nationality_codes'] ?? []))));
                        if ($codes !== []) {
                            $ids = Country::whereIn('code', $codes)->pluck('id')->all();
                            if ($ids !== []) {
                                $changed = true;
                                $stats['nationalities']++;
                                if (! $dry) {
                                    $cycle->nationalities()->syncWithoutDetaching($ids);
                                }
                            }
                        } elseif (($e['any_nationality'] ?? null) === true && ! $cycle->any_nationality) {
                            $changed = true;
                            $stats['any_nationality']++;
                            if (! $dry) {
                                $cycle->forceFill(['any_nationality' => true])->save();
                            }
                        }
                    }

                    foreach ((array) ($e['milestones'] ?? []) as $m) {
                        $kind = (string) ($m['kind'] ?? '');
                        $label = trim((string) ($m['label'] ?? ''));
                        $type = (string) ($m['date_type'] ?? '');
                        if (! isset(CycleMilestone::KINDS[$kind]) || $label === '' || ! isset(CycleMilestone::DATE_TYPES[$type]) || blank($m['source_text'] ?? null)) {
                            $stats['skipped']++;

                            continue;
                        }
                        if ($cycle->milestones()->where('kind', $kind)->where('label', $label)->exists()) {
                            continue;
                        }
                        $changed = true;
                        $stats['milestones']++;
                        if (! $dry) {
                            $cycle->milestones()->create([
                                'kind' => $kind, 'label' => mb_substr($label, 0, 250), 'date_type' => $type,
                                'starts_on' => $m['starts_on'] ?? null, 'ends_on' => $m['ends_on'] ?? null,
                                'time_text' => filled($m['time_text'] ?? null) ? mb_substr((string) $m['time_text'], 0, 190) : null,
                                'source_text' => trim((string) $m['source_text']), 'source_url' => filled($m['source_url'] ?? null) ? mb_substr((string) $m['source_url'], 0, 500) : null,
                                'sort' => (int) $cycle->milestones()->max('sort') + 10,
                            ]);
                        }
                    }
                });
                $stats['cycles'] += $changed ? 1 : 0;
            }
        }

        $this->info(($dry ? '[dry run] ' : '')."{$stats['cycles']} scholarship(s) changed: {$stats['language']} language, {$stats['nationalities']} country list(s), {$stats['any_nationality']} open-to-all, {$stats['milestones']} date(s); {$stats['unknown']} unknown key(s), {$stats['skipped']} date(s) skipped.");

        return self::SUCCESS;
    }
}
