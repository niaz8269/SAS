<?php

namespace App\Console\Commands;

use App\Domain\Scholarship\SearchIndexer;
use App\Models\CycleOffering;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Imports what each scholarship offers at each degree level (a summary line per level plus the degree programmes the official
 * sources name) and aligns the scholarship's degree levels with what the sources confirm. A level that is not confirmed is left
 * as it was unless the source excludes it; offerings are replaced as a whole, so the command is safe to run again.
 */
class ImportOfferings extends Command
{
    protected $signature = 'sas:import-offerings {files* : JSON files from the degree-programme pass} {--report= : write a Markdown report to this path} {--dry-run : report only, change nothing}';

    protected $description = 'Import degree levels and degree programmes offered by each scholarship from official sources.';

    public function handle(SearchIndexer $indexer): int
    {
        $levels = DegreeLevel::pluck('id', 'code');
        $dry = (bool) $this->option('dry-run');
        $report = [];
        $totals = ['cycles' => 0, 'rows' => 0, 'level_changes' => 0, 'missing' => 0];

        foreach ($this->argument('files') as $file) {
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (! is_array($data)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            foreach ($data as $e) {
                $cycle = ScholarshipCycle::where('external_key', $e['external_key'] ?? '')->with('degreeLevels')->first();
                if (! $cycle) {
                    $totals['missing']++;
                    $report[$e['external_key'] ?? '?'] = ['lines' => ['No such scholarship.'], 'notes' => []];

                    continue;
                }
                $lines = [];
                $rows = [];
                $confirmed = [];
                foreach ($e['levels'] ?? [] as $lv) {
                    $code = $lv['code'] ?? '';
                    if (! isset($levels[$code]) || empty($lv['confirmed'])) {
                        continue;
                    }
                    $confirmed[] = $code;
                    $sort = 0;
                    $s = $lv['summary'] ?? [];
                    if (! empty($s['detail']) || ! empty($s['source_text'])) {
                        $rows[] = $this->row($cycle, $levels[$code], null, null, $s['detail'] ?? null, $s['source_text'] ?? null, $s['url'] ?? null, $sort += 10);
                    }
                    foreach (array_slice($lv['programmes'] ?? [], 0, 25) as $p) {
                        if (blank($p['name'] ?? null)) {
                            continue;
                        }
                        $rows[] = $this->row($cycle, $levels[$code], $p['name'], $p['institution'] ?? null, $p['detail'] ?? null, $p['source_text'] ?? null, $p['url'] ?? null, $sort += 10);
                    }
                }

                $now = $cycle->degreeLevels->pluck('code')->all();
                $excluded = array_values(array_intersect($e['levels_not_offered'] ?? [], array_keys($levels->all())));
                $target = array_values(array_diff(array_values(array_unique(array_merge($now, $confirmed))), $excluded));
                sort($target);
                $before = $now;
                sort($before);
                if ($target !== $before && $target !== []) {
                    $lines[] = 'Degree levels: '.implode(', ', $before ?: ['none']).' → '.implode(', ', $target);
                    $totals['level_changes']++;
                    if (! $dry) {
                        $cycle->degreeLevels()->sync(collect($target)->map(fn ($c) => $levels[$c])->all());
                    }
                }
                if ($rows !== []) {
                    $named = count(array_filter($rows, fn ($r) => $r['name'] !== null));
                    $lines[] = count($rows).' row(s) for '.implode(', ', array_unique($confirmed)).($named ? " ({$named} named programme(s))" : ' (summary only)');
                    $totals['cycles']++;
                    $totals['rows'] += count($rows);
                    if (! $dry) {
                        $cycle->offerings()->delete();
                        foreach ($rows as $r) {
                            CycleOffering::create($r);
                        }
                    }
                }
                if (! $dry && ($rows !== [] || $target !== $before)) {
                    $indexer->reindex($cycle->fresh(['programme']));
                }
                $notes = $e['notes'] ?? [];
                if (! empty($e['catalogue_url'])) {
                    $notes[] = 'Full programme list: '.$e['catalogue_url'];
                }
                $report[$e['external_key']] = ['status' => $e['status'] ?? '?', 'lines' => $lines, 'notes' => $notes];
            }
        }

        $this->info(sprintf('%d scholarships with offerings (%d rows), %d degree-level changes, %d unknown keys.', $totals['cycles'], $totals['rows'], $totals['level_changes'], $totals['missing']));
        if ($path = $this->option('report')) {
            $md = "# Degree levels and programmes offered, 23 October 2026\n\n".($dry ? "_Dry run: nothing was changed._\n\n" : '')
                ."Each scholarship's degree levels and the degree programmes it offers were checked against official pages. A level that no official page confirms is left as it was unless an official page excludes it.\n\n";
            foreach ($report as $key => $r) {
                $md .= "## {$key}\n\n".(isset($r['status']) ? "Status: {$r['status']}\n\n" : '');
                foreach ($r['lines'] as $l) {
                    $md .= "- {$l}\n";
                }
                foreach ($r['notes'] as $n) {
                    $md .= "- _Note:_ {$n}\n";
                }
                $md .= "\n";
            }
            file_put_contents($path, $md);
            $this->line("Report written to {$path}");
        }

        return self::SUCCESS;
    }

    private function row(ScholarshipCycle $cycle, int $levelId, ?string $name, ?string $institution, ?string $detail, ?string $quote, ?string $url, int $sort): array
    {
        return [
            'cycle_id' => $cycle->id, 'degree_level_id' => $levelId,
            'name' => $name ? Str::limit(trim($name), 195, '') : null,
            'institution' => $institution ? Str::limit(trim($institution), 195, '') : null,
            'detail' => $detail ? Str::limit(trim($detail), 1990, '') : null,
            'source_text' => $quote ? Str::limit(trim($quote), 590, '') : null,
            'source_url' => $url && preg_match('#^https?://#i', $url) ? Str::limit($url, 495, '') : null,
            'sort' => $sort,
        ];
    }
}
