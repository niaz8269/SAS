<?php

namespace App\Console\Commands;

use App\Domain\Scholarship\ChangeDeadline;
use App\Domain\Scholarship\DeadlineService;
use App\Domain\Scholarship\SearchIndexer;
use App\Models\CycleMilestone;
use App\Models\DegreeLevel;
use App\Models\ScholarshipCycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Applies the result of an official-source verification pass (title, degree levels, application opening and closing date) to
 * the scholarships and writes a report of what changed and what could not be confirmed. A date is only ever replaced by another
 * date: "not announced" in the report never erases a recorded date. Closing-date changes go through the versioned deadline
 * service, so the old date stays in the history and reminders are rescheduled.
 */
class ApplyVerification extends Command
{
    protected $signature = 'sas:apply-verification {files* : JSON files from the verification pass} {--by= : staff email recorded as the actor} {--report= : write a Markdown report to this path} {--dry-run : report only, change nothing}';

    protected $description = 'Apply official-source verification results to scholarship titles, degree levels and dates.';

    public function handle(ChangeDeadline $change, DeadlineService $deadlines, SearchIndexer $indexer): int
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
        $actor = $this->option('by') ? User::where('email', $this->option('by'))->first() : null;
        $dry = (bool) $this->option('dry-run');
        $report = [];
        $totals = ['titles' => 0, 'degrees' => 0, 'opens' => 0, 'closes' => 0, 'unconfirmed' => 0];

        foreach ($entries as $key => $e) {
            $cycle = ScholarshipCycle::where('external_key', $key)->with(['programme', 'degreeLevels', 'currentDeadlines', 'milestones'])->first();
            if (! $cycle) {
                $report[$key] = ['status' => 'no such scholarship'];

                continue;
            }
            $lines = [];

            // Title
            $new = trim((string) ($e['official_title'] ?? ''));
            if (($e['title_action'] ?? 'keep') === 'change' && $new !== '' && $new !== $cycle->programme->title) {
                $lines[] = "Title: “{$cycle->programme->title}” → “{$new}”";
                if (! $dry) {
                    $cycle->programme->forceFill(['title' => $new])->save();
                }
                $totals['titles']++;
            }

            // Degree levels
            $deg = $e['degrees'] ?? [];
            if (! empty($deg['confirmed']) && ! empty($deg['codes'])) {
                $ids = DegreeLevel::whereIn('code', $deg['codes'])->pluck('id', 'code');
                $codes = $ids->keys()->sort()->values()->all();
                $now = $cycle->degreeLevels->pluck('code')->sort()->values()->all();
                if ($codes !== $now && $ids->isNotEmpty()) {
                    $lines[] = 'Degrees: '.implode(', ', $now ?: ['none']).' → '.implode(', ', $codes);
                    if (! $dry) {
                        $cycle->degreeLevels()->sync($ids->values()->all());
                    }
                    $totals['degrees']++;
                }
            }

            // Application opening
            $o = $e['opens'] ?? [];
            $type = $o['date_type'] ?? 'not_announced';
            if (in_array($type, ['exact', 'date_only', 'month', 'range', 'route_dependent'], true) && (! empty($o['starts_on']) || $type === 'route_dependent')) {
                $current = $cycle->milestones->firstWhere('kind', 'opens');
                $newValues = ['date_type' => $type, 'starts_on' => $o['starts_on'] ?? null, 'ends_on' => $o['ends_on'] ?? null, 'time_text' => in_array($type, ['month', 'range'], true) ? ($o['phrase'] ?? null) : ($current?->time_text),
                    'source_text' => isset($o['source_text']) ? mb_substr($o['source_text'], 0, 590) : null, 'source_url' => $o['url'] ?? null];
                $same = $current && $current->date_type === $type && optional($current->starts_on)->toDateString() === ($o['starts_on'] ?? null) && optional($current->ends_on)->toDateString() === ($o['ends_on'] ?? null)
                    && (! in_array($type, ['month', 'range'], true) || empty($o['phrase']) || $current->time_text === $o['phrase']);
                // As with closing dates, a recorded date is not replaced by "depends on route": it is reported instead.
                $keepDated = $type === 'route_dependent' && $current && in_array($current->date_type, ['exact', 'date_only', 'month', 'range'], true) && $current->starts_on;
                if ($keepDated) {
                    $lines[] = 'Opens: official pages say depends on route; recorded date kept ('.$current->display().')';
                } elseif (! $same) {
                    $lines[] = 'Opens: '.($current?->display() ?? 'none recorded').' → '.(new CycleMilestone($newValues))->display().(! empty($o['call_year']) ? " (call {$o['call_year']})" : '');
                    if (! $dry) {
                        $current ? $current->update($newValues) : CycleMilestone::create($newValues + ['cycle_id' => $cycle->id, 'kind' => 'opens', 'label' => 'Applications open', 'sort' => 5]);
                    }
                    $totals['opens']++;
                }
            } elseif ($type === 'not_announced') {
                $lines[] = 'Opens: official pages give no date'.(($c = $cycle->milestones->firstWhere('kind', 'opens')) && $c->date_type !== 'not_announced' ? ' (recorded date kept: '.$c->display().')' : '');
            }

            // Application closing
            $c = $e['closes'] ?? [];
            $ctype = $c['type'] ?? 'not_announced';
            $currentDeadline = $cycle->currentDeadlines->first(fn ($d) => $d->route_id === null) ?? $cycle->currentDeadlines->first();
            $input = null;
            if ($ctype === 'exact' && ! empty($c['local_datetime']) && ! empty($c['timezone'])) {
                $input = ['date_type' => 'exact', 'local_closes_at' => $c['local_datetime'], 'source_timezone' => $c['timezone'], 'source_text' => $c['source_text'] ?? null];
            } elseif ($ctype === 'date_only' && ! empty($c['date'])) {
                $input = ['date_type' => 'date_only', 'closes_on' => $c['date'], 'source_text' => $c['source_text'] ?? null];
            } elseif (in_array($ctype, ['rolling', 'route_dependent'], true)) {
                $input = ['date_type' => $ctype, 'source_text' => $c['source_text'] ?? null];
            }
            if ($input) {
                $differs = ! $currentDeadline || $currentDeadline->date_type->value !== $input['date_type']
                    || ($input['date_type'] === 'date_only' && optional($currentDeadline->closes_on)->toDateString() !== $input['closes_on'])
                    || ($input['date_type'] === 'exact' && (! $currentDeadline->closes_at_utc || ! CarbonImmutable::parse($input['local_closes_at'], $input['source_timezone'])->utc()->equalTo($currentDeadline->closes_at_utc)));
                // A dated deadline is never replaced by "varies by route" or "rolling" without a date: report it instead.
                $downgrade = $currentDeadline && in_array($currentDeadline->date_type->value, ['exact', 'date_only'], true) && in_array($input['date_type'], ['rolling', 'route_dependent'], true);
                if ($differs && ! $downgrade) {
                    $before = $currentDeadline ? $deadlines->describe($currentDeadline)['primary'] : 'none recorded';
                    $lines[] = "Closes: {$before} → ".($input['date_type'] === 'exact' ? $input['local_closes_at'].' '.$input['source_timezone'] : ($input['date_type'] === 'date_only' ? $input['closes_on'] : $input['date_type'])).(! empty($c['call_year']) ? " (call {$c['call_year']})" : '');
                    if (! $dry) {
                        try {
                            $change($cycle, $input, $actor, 'Verified against the official source on 2026-10-23: '.($c['url'] ?? ''), $currentDeadline?->route_id);
                        } catch (\Throwable $ex) {
                            $lines[] = 'Closes: could not apply ('.$ex->getMessage().')';
                        }
                    }
                    $totals['closes']++;
                } elseif ($downgrade && $differs) {
                    $lines[] = "Closes: official pages say {$input['date_type']}; recorded date kept";
                }
            } elseif ($ctype === 'not_announced') {
                $lines[] = 'Closes: official pages give no date'.($currentDeadline && in_array($currentDeadline->date_type->value, ['exact', 'date_only'], true) ? ' (recorded date kept)' : '');
            }

            $status = $e['status'] ?? 'unconfirmed';
            if ($status === 'unconfirmed') {
                $totals['unconfirmed']++;
            }
            if (! $dry && in_array($status, ['confirmed', 'partly'], true)) {
                $cycle->forceFill(['last_verified_at' => now()])->saveQuietly();
                $cycle->evidence()->update(['checked_at' => now()]);
                $indexer->reindex($cycle->fresh(['programme']));
                $deadlines->refresh($cycle->fresh());
            }
            $report[$key] = ['status' => $status, 'changes' => $lines, 'notes' => $e['notes'] ?? [], 'title' => $new ?: $cycle->programme->title];
        }

        $this->info(sprintf('%d scholarships; changed: %d titles, %d degree lists, %d opening dates, %d closing dates; %d not confirmed.', count($entries),
            $totals['titles'], $totals['degrees'], $totals['opens'], $totals['closes'], $totals['unconfirmed']));
        if ($path = $this->option('report')) {
            file_put_contents($path, $this->markdown($report, $totals, $dry));
            $this->line("Report written to {$path}");
        }

        return self::SUCCESS;
    }

    private function markdown(array $report, array $totals, bool $dry): string
    {
        $md = "# Official-source verification, 23 October 2026\n\n".($dry ? "_Dry run: nothing was changed._\n\n" : '')
            .'Each scholarship was checked against official pages (funder, government, embassy, agency or host university) for its title, degree levels, application opening and closing dates. '
            ."Dates already recorded are never erased by \"not announced\"; closing-date changes are kept in the deadline history.\n\n"
            .sprintf("Changed: %d titles, %d degree lists, %d opening dates, %d closing dates. Not confirmed: %d.\n\n", $totals['titles'], $totals['degrees'], $totals['opens'], $totals['closes'], $totals['unconfirmed']);
        foreach ($report as $key => $r) {
            $md .= "## {$key}\n\n".(is_array($r) && isset($r['title']) ? "**{$r['title']}** — status: {$r['status']}\n\n" : "{$r['status']}\n\n");
            foreach ($r['changes'] ?? [] as $l) {
                $md .= "- {$l}\n";
            }
            foreach ($r['notes'] ?? [] as $n) {
                $md .= "- _Note:_ {$n}\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
