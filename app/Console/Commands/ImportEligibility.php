<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Domain\Editorial\ScholarshipImporter;
use App\Domain\Scholarship\SearchIndexer;
use App\Models\Country;
use App\Models\CycleBenefit;
use App\Models\CycleCriterion;
use App\Models\CycleRequirement;
use App\Models\ScholarshipCycle;
use App\Models\SourceEvidence;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds who can apply and what the scholarship offers, from researched JSON files: eligible (or excluded) nationalities, subjects,
 * eligibility and age notes, published criteria, funding components and required documents, each backed by official source links.
 *
 * Rules: nothing is guessed (a mode of "unconfirmed" or a missing key leaves the field as it is); "any" is only set when the source says so;
 * lists and components given in the file replace those stored (so a re-run changes nothing); a known funding type is never changed
 * (only "unknown" can become known); "fully funded" is never set (that needs a reviewer); publication state is never touched.
 */
class ImportEligibility extends Command
{
    protected $signature = 'sas:import-eligibility {files* : JSON files (arrays of entries)} {--by= : staff email recorded as the actor} {--report= : write a Markdown report to this path} {--dry-run : report only, change nothing}';

    protected $description = 'Import researched eligibility (nationalities, subjects, criteria), funding components and required documents.';

    private const BENEFIT_TYPES = ['tuition', 'living_allowance', 'accommodation', 'travel', 'insurance', 'other'];

    private const COVERAGE = ['covered', 'partial', 'not_covered', 'unknown'];

    private const CRITERION_TYPES = ['min_gpa', 'language', 'age'];

    private const OPERATORS = ['>=', '<=', '='];

    private const FUNDING = ['full', 'partial', 'tuition_only', 'stipend_only', 'other'];

    public function handle(SearchIndexer $indexer): int
    {
        $entries = [];
        foreach ($this->argument('files') as $file) {
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (! is_array($data)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            foreach ($data as $e) {
                if (! empty($e['external_key'])) {
                    $entries[$e['external_key']] = $e;
                }
            }
        }
        $dry = (bool) $this->option('dry-run');
        $actor = $this->option('by') ? User::where('email', $this->option('by'))->first() : null;
        $countries = Country::pluck('id', 'code')->all();
        $subjects = Subject::pluck('id', 'slug')->all();
        $report = [];
        $totals = ['cycles' => 0, 'nationality_lists' => 0, 'any_nationality' => 0, 'excluded' => 0, 'subjects' => 0, 'criteria' => 0, 'benefits' => 0, 'requirements' => 0, 'missing' => 0, 'warnings' => 0];

        foreach ($entries as $key => $e) {
            $cycle = ScholarshipCycle::where('external_key', $key)->first();
            if (! $cycle) {
                $totals['missing']++;
                $report[$key] = ['lines' => ['No such scholarship.'], 'warnings' => []];

                continue;
            }
            $lines = [];
            $warn = [];
            $set = [];

            // Nationality
            $nat = $e['nationality'] ?? [];
            $mode = $nat['mode'] ?? 'unconfirmed';
            $natIds = $this->ids($nat['codes'] ?? [], $countries, $warn, 'nationality code');
            $exIds = $this->ids($nat['excluded_codes'] ?? [], $countries, $warn, 'excluded nationality code');
            if ($mode === 'any') {
                $lines[] = 'Nationality: open to any'.($exIds ? ' except '.count($exIds).' excluded' : '');
                $set['any_nationality'] = true;
                $totals['any_nationality']++;
            } elseif ($mode === 'list' && $natIds) {
                $lines[] = 'Nationality: '.count($natIds).' eligible countries listed';
                $set['any_nationality'] = false;
                $totals['nationality_lists']++;
            } elseif ($mode === 'list') {
                $warn[] = 'Nationality list was empty, left as it was.';
            }
            if ($exIds && $mode === 'any') {
                $totals['excluded']++;
            } elseif ($exIds) {
                $warn[] = 'Excluded nationalities only apply to calls open to any nationality; ignored.';
                $exIds = [];
            }

            // Subjects
            $sub = $e['subjects'] ?? [];
            $smode = $sub['mode'] ?? 'unconfirmed';
            $subIds = [];
            foreach ($sub['slugs'] ?? [] as $slug) {
                isset($subjects[$slug]) ? $subIds[] = $subjects[$slug] : $warn[] = "Unknown subject '{$slug}' ignored.";
            }
            if ($smode === 'any') {
                $set['any_subject'] = true;
                $lines[] = 'Subjects: any';
                $totals['subjects']++;
            } elseif ($smode === 'list' && $subIds) {
                $set['any_subject'] = false;
                $lines[] = 'Subjects: '.implode(', ', $sub['slugs']);
                $totals['subjects']++;
            }

            foreach (['eligibility_notes' => 2000, 'age_experience_notes' => 1000] as $field => $max) {
                if (! empty($e[$field]) && trim($e[$field]) !== (string) $cycle->{$field}) {
                    $set[$field] = Str::limit(trim($e[$field]), $max, '');
                    $lines[] = ucfirst(str_replace('_', ' ', $field)).' written';
                }
            }

            // Funding: only unknown can become known; the summary is only written where there is none.
            $fund = $e['funding'] ?? [];
            if (! empty($fund['type']) && in_array($fund['type'], self::FUNDING, true)) {
                if ($cycle->funding_type === 'unknown') {
                    $set['funding_type'] = $fund['type'];
                    $lines[] = "Funding type: unknown → {$fund['type']}";
                } elseif ($cycle->funding_type !== $fund['type']) {
                    $warn[] = "Source suggests funding type '{$fund['type']}' but '{$cycle->funding_type}' is recorded; not changed.";
                }
            }
            if (! empty($fund['summary']) && blank($cycle->funding_summary)) {
                $set['funding_summary'] = Str::limit(trim($fund['summary']), 1000, '');
            }
            if (! empty($e['teaching_language']) && blank($cycle->teaching_language)) {
                $set['teaching_language'] = Str::limit(trim($e['teaching_language']), 120, '');
            }

            // Structured parts
            $criteria = $this->criteria($e['criteria'] ?? [], $warn);
            $benefits = $this->benefits($e['benefits'] ?? [], $warn);
            $requirements = $this->requirements($e['requirements'] ?? [], $warn);
            $criteria && $lines[] = count($criteria).' criteria';
            $benefits && $lines[] = count($benefits).' funding components';
            $requirements && $lines[] = count($requirements).' required documents';
            $totals['criteria'] += count($criteria);
            $totals['benefits'] += count($benefits);
            $totals['requirements'] += count($requirements);

            $sources = $this->sources($e['sources'] ?? [], $warn);
            $status = $e['status'] ?? 'unconfirmed';

            if (! $dry) {
                DB::transaction(function () use ($cycle, $set, $mode, $natIds, $exIds, $smode, $subIds, $criteria, $benefits, $requirements, $sources, $status, $e) {
                    $set && $cycle->forceFill($set)->save();
                    if ($mode === 'list' && $natIds) {
                        $cycle->nationalities()->sync($natIds);
                    }
                    if ($mode === 'any') {
                        $cycle->nationalities()->sync([]);
                        $cycle->excludedNationalities()->sync($exIds);
                    }
                    if ($smode === 'list' && $subIds) {
                        $cycle->subjects()->sync($subIds);
                    }
                    if ($smode === 'any') {
                        $cycle->subjects()->sync([]);
                    }
                    if ($criteria || ! empty($e['criteria'])) {
                        $cycle->criteria()->delete();
                        foreach ($criteria as $c) {
                            CycleCriterion::create($c + ['cycle_id' => $cycle->id]);
                        }
                    }
                    if ($benefits) {
                        $cycle->benefits()->delete();
                        foreach ($benefits as $i => $b) {
                            CycleBenefit::create($b + ['cycle_id' => $cycle->id, 'sort' => ($i + 1) * 10]);
                        }
                    }
                    if ($requirements) {
                        $cycle->requirements()->delete();
                        foreach ($requirements as $i => $r) {
                            CycleRequirement::create($r + ['cycle_id' => $cycle->id, 'sort' => ($i + 1) * 10]);
                        }
                    }
                    foreach ($sources as $s) {
                        $ev = SourceEvidence::firstOrNew(['cycle_id' => $cycle->id, 'url' => $s['url']]);
                        $ev->fill(['publisher' => $s['publisher'], 'publisher_type' => $s['publisher_type'], 'checked_at' => CarbonImmutable::parse($e['checked_on'] ?? now()->toDateString()),
                            'note' => 'Eligibility and funding details read from this page']);
                        $ev->save();
                    }
                    if (in_array($status, ['confirmed', 'partly'], true)) {
                        $cycle->forceFill(['last_verified_at' => now()])->saveQuietly();
                    }
                });
                AuditLog::record('cycle.eligibility_imported', $cycle, null, ['status' => $status], null, $actor);
                $indexer->reindex($cycle->fresh(['programme']));
            }
            $totals['cycles']++;
            $totals['warnings'] += count($warn);
            $report[$key] = ['lines' => $lines, 'warnings' => array_merge($warn, $e['notes'] ?? []), 'status' => $status];
        }

        $this->info(sprintf('%d scholarships: %d with a nationality list, %d open to any (%d with exclusions), %d with subjects, %d criteria, %d funding components, %d required documents; %d unknown keys; %d warnings.',
            $totals['cycles'], $totals['nationality_lists'], $totals['any_nationality'], $totals['excluded'], $totals['subjects'], $totals['criteria'], $totals['benefits'], $totals['requirements'], $totals['missing'], $totals['warnings']));
        if ($path = $this->option('report')) {
            $md = "# Eligibility and funding details\n\n".($dry ? "_Dry run: nothing was changed._\n\n" : '');
            foreach ($report as $key => $r) {
                $md .= "## {$key}\n\n".(isset($r['status']) ? "Status: {$r['status']}\n\n" : '');
                foreach ($r['lines'] as $l) {
                    $md .= "- {$l}\n";
                }
                foreach ($r['warnings'] as $w) {
                    $md .= "- _Note:_ {$w}\n";
                }
                $md .= "\n";
            }
            file_put_contents($path, $md);
            $this->line("Report written to {$path}");
        }

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function ids(array $codes, array $countries, array &$warn, string $what): array
    {
        $ids = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));
            isset($countries[$code]) ? $ids[] = $countries[$code] : ($code !== '' && $warn[] = "Unknown {$what} '{$code}' ignored.");
        }

        return array_values(array_unique($ids));
    }

    private function criteria(array $rows, array &$warn): array
    {
        $out = [];
        foreach ($rows as $r) {
            $type = $r['type'] ?? '';
            $op = $r['operator'] ?? '';
            $value = trim((string) ($r['value'] ?? ''));
            if (! in_array($type, self::CRITERION_TYPES, true) || ! in_array($op, self::OPERATORS, true) || ! is_numeric($value)) {
                $warn[] = 'A criterion was skipped (needs a type min_gpa, language or age, an operator and a numeric value; anything else belongs in the eligibility notes).';

                continue;
            }
            $out[] = ['type' => $type, 'operator' => $op, 'value' => Str::limit($value, 32, ''), 'unit' => isset($r['unit']) ? Str::limit(trim((string) $r['unit']), 32, '') : null,
                'mandatory' => (bool) ($r['mandatory'] ?? true), 'evidence' => isset($r['evidence']) ? Str::limit(trim((string) $r['evidence']), 500, '') : null];
        }

        // The same kind of criterion with different values (a different minimum per degree level or per route) cannot be one rule for everybody:
        // it stays in the eligibility notes instead of wrongly ruling people out.
        $keyOf = fn (array $c) => $c['type'].'|'.$c['operator'].'|'.mb_strtolower((string) $c['unit']);
        foreach (collect($out)->groupBy($keyOf) as $key => $rows) {
            if ($rows->pluck('value')->unique()->count() > 1) {
                $warn[] = "Criteria '{$key}' have different values (".$rows->pluck('value')->implode(', ').'); left out so the notes carry them.';
                $out = array_values(array_filter($out, fn (array $c) => $keyOf($c) !== $key));
            }
        }

        return $out;
    }

    private function benefits(array $rows, array &$warn): array
    {
        $out = [];
        foreach ($rows as $r) {
            $type = $r['type'] ?? '';
            if (! in_array($type, self::BENEFIT_TYPES, true)) {
                $warn[] = "A funding component was skipped (type '{$type}').";

                continue;
            }
            $amount = isset($r['amount']) && is_numeric($r['amount']) ? (int) round(((float) $r['amount']) * 100) : null;
            $currency = $amount !== null && preg_match('/^[A-Za-z]{3}$/', (string) ($r['currency'] ?? '')) ? strtoupper($r['currency']) : null;
            $out[] = ['type' => $type, 'coverage' => in_array($r['coverage'] ?? '', self::COVERAGE, true) ? $r['coverage'] : 'unknown',
                'limit_text' => isset($r['limit_text']) ? Str::limit(trim((string) $r['limit_text']), 250, '') : null,
                'amount_minor' => $currency ? $amount : null, 'currency' => $currency,
                'conditions' => isset($r['conditions']) ? Str::limit(trim((string) $r['conditions']), 1000, '') : null];
        }

        return $out;
    }

    private function requirements(array $rows, array &$warn): array
    {
        $out = [];
        foreach ($rows as $r) {
            $text = trim((string) ($r['text'] ?? ''));
            if ($text === '') {
                $warn[] = 'A required document without text was skipped.';

                continue;
            }
            $out[] = ['document_type' => Str::limit((string) ($r['document_type'] ?? 'other'), 48, '') ?: 'other', 'text' => Str::limit($text, 500, ''),
                'conditional_rule' => isset($r['conditional_rule']) && trim($r['conditional_rule']) !== '' ? Str::limit(trim($r['conditional_rule']), 500, '') : null, 'mandatory' => (bool) ($r['mandatory'] ?? true)];
        }

        return $out;
    }

    private function sources(array $rows, array &$warn): array
    {
        $out = [];
        $importer = new ScholarshipImporter;
        foreach ($rows as $r) {
            $type = $r['publisher_type'] ?? 'government';
            if (empty($r['url']) || ! $importer->safeUrl($r['url']) || ! in_array($type, ['funder', 'government', 'embassy', 'university'], true)) {
                $warn[] = 'A source was skipped (needs a public http(s) address and a publisher type funder, government, embassy or university; aggregators do not count).';

                continue;
            }
            $out[] = ['url' => $r['url'], 'publisher' => Str::limit((string) ($r['publisher'] ?? ''), 190, ''), 'publisher_type' => $type];
        }

        return $out;
    }
}
