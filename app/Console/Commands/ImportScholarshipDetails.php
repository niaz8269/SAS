<?php

namespace App\Console\Commands;

use App\Domain\Scholarship\SearchIndexer;
use App\Domain\Seo\Sitemap;
use App\Models\CycleApplicationStep;
use App\Models\CycleBenefit;
use App\Models\CycleRequirement;
use App\Models\ScholarshipCycle;
use App\Models\SourceEvidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads the details of a scholarship as its official sources state them: text fields, funding components, required documents, the
 * application process in steps, degree levels with the programmes offered (through sas:import-offerings), and the sources read.
 * A section that is absent from the file is left as it is; a section that is present replaces the stored one as a whole, so the command
 * is safe to run again. Nothing is published or unpublished here, and funding is never marked as verified here.
 */
class ImportScholarshipDetails extends Command
{
    protected $signature = 'sas:import-details {files* : JSON files, one entry per scholarship} {--dry-run : report only, change nothing}';

    protected $description = 'Import complete scholarship details (funding, documents, application steps, programmes, sources) from official sources.';

    private const BENEFIT_TYPES = ['tuition', 'accommodation', 'living_allowance', 'insurance', 'travel', 'other'];

    private const COVERAGE = ['covered', 'partial', 'not_covered', 'unknown'];

    private const DOCUMENT_TYPES = ['other', 'passport', 'degree_certificate', 'transcript', 'language_certificate', 'research_proposal', 'reference', 'medical', 'cv', 'statement', 'photo'];

    private const TEXT_FIELDS = ['summary', 'eligibility_notes', 'age_experience_notes', 'programmes_note', 'institutions_note', 'funding_summary'];

    private const PUBLISHER_TYPES = ['government', 'university', 'funder', 'embassy'];

    public function handle(SearchIndexer $indexer): int
    {
        $dry = (bool) $this->option('dry-run');
        $files = $this->argument('files');
        $totals = ['scholarships' => 0, 'missing' => 0, 'steps' => 0, 'benefits' => 0, 'requirements' => 0, 'sources' => 0];

        if (! $dry) {
            Artisan::call('sas:import-offerings', ['files' => $files]); // degree levels and programmes offered
        }

        foreach ($files as $file) {
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : null;
            if (! is_array($data)) {
                $this->error("Cannot read {$file}");

                return self::FAILURE;
            }
            foreach ($data as $e) {
                $cycle = ScholarshipCycle::where('external_key', $e['external_key'] ?? '')->first();
                if (! $cycle) {
                    $totals['missing']++;
                    $this->warn('No such scholarship: '.($e['external_key'] ?? '?'));

                    continue;
                }
                $totals['scholarships']++;
                $steps = $this->steps($e['application_steps'] ?? []);
                $benefits = $this->benefits($e['benefits'] ?? []);
                $requirements = $this->requirements($e['requirements'] ?? []);
                $sources = $this->sources($e['sources'] ?? [], $e['checked_on'] ?? null);
                $text = collect(self::TEXT_FIELDS)->filter(fn ($f) => filled($e[$f] ?? null))->mapWithKeys(fn ($f) => [$f => trim((string) $e[$f])])->all();
                $this->line(sprintf('%s: %d steps, %d benefits, %d documents, %d sources, text: %s', $cycle->external_key, count($steps), count($benefits), count($requirements), count($sources), implode(', ', array_keys($text)) ?: 'none'));
                if ($dry) {
                    continue;
                }

                DB::transaction(function () use ($cycle, $text, $steps, $benefits, $requirements, $sources, $e) {
                    if ($text) {
                        $cycle->forceFill($text);
                    }
                    if (! empty($e['checked_on']) && strtotime($e['checked_on'])) {
                        $cycle->last_verified_at = $e['checked_on'];
                    }
                    $cycle->save();
                    if ($steps) {
                        $cycle->applicationSteps()->delete();
                        foreach ($steps as $i => $s) {
                            CycleApplicationStep::create($s + ['cycle_id' => $cycle->id, 'sort' => ($i + 1) * 10]);
                        }
                    }
                    if ($benefits) {
                        CycleBenefit::where('cycle_id', $cycle->id)->delete();
                        foreach ($benefits as $i => $b) {
                            CycleBenefit::create($b + ['cycle_id' => $cycle->id, 'sort' => ($i + 1) * 10]);
                        }
                    }
                    if ($requirements) {
                        CycleRequirement::where('cycle_id', $cycle->id)->delete();
                        foreach ($requirements as $i => $r) {
                            CycleRequirement::create($r + ['cycle_id' => $cycle->id, 'sort' => ($i + 1) * 10]);
                        }
                    }
                    foreach ($sources as $s) {
                        $existing = SourceEvidence::where('cycle_id', $cycle->id)->where('url', $s['url'])->first();
                        $existing ? $existing->update(['checked_at' => $s['checked_at']]) : SourceEvidence::create($s + ['cycle_id' => $cycle->id]);
                    }
                });
                $totals['steps'] += count($steps);
                $totals['benefits'] += count($benefits);
                $totals['requirements'] += count($requirements);
                $totals['sources'] += count($sources);
                $indexer->reindex($cycle->fresh(['programme']));
            }
        }
        if (! $dry) {
            Sitemap::forget();
        }
        $this->info(sprintf('%d scholarships updated, %d unknown keys; %d steps, %d benefits, %d documents, %d sources.', $totals['scholarships'], $totals['missing'], $totals['steps'], $totals['benefits'], $totals['requirements'], $totals['sources']));

        return self::SUCCESS;
    }

    private function steps(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (blank($r['title'] ?? null) || ! isset(CycleApplicationStep::PHASES[$r['phase'] ?? ''])) {
                continue;
            }
            $out[] = ['phase' => $r['phase'], 'title' => Str::limit(trim($r['title']), 195, ''), 'detail' => filled($r['detail'] ?? null) ? trim($r['detail']) : null, 'source_url' => $this->url($r['source_url'] ?? null)];
        }

        return $out;
    }

    private function benefits(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! in_array($r['type'] ?? '', self::BENEFIT_TYPES, true)) {
                continue;
            }
            $out[] = ['type' => $r['type'], 'coverage' => in_array($r['coverage'] ?? '', self::COVERAGE, true) ? $r['coverage'] : 'unknown',
                'limit_text' => filled($r['limit_text'] ?? null) ? Str::limit(trim($r['limit_text']), 250, '') : null, 'conditions' => filled($r['conditions'] ?? null) ? trim($r['conditions']) : null];
        }

        return $out;
    }

    private function requirements(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (blank($r['text'] ?? null)) {
                continue;
            }
            $out[] = ['document_type' => in_array($r['document_type'] ?? '', self::DOCUMENT_TYPES, true) ? $r['document_type'] : 'other', 'text' => trim($r['text']),
                'conditional_rule' => filled($r['conditional_rule'] ?? null) ? trim($r['conditional_rule']) : null, 'mandatory' => (bool) ($r['mandatory'] ?? true)];
        }

        return $out;
    }

    private function sources(array $rows, ?string $checked): array
    {
        $out = [];
        foreach ($rows as $r) {
            $url = $this->url($r['url'] ?? null);
            if (! $url || blank($r['publisher'] ?? null)) {
                continue;
            }
            $out[] = ['url' => $url, 'publisher' => Str::limit(trim($r['publisher']), 190, ''), 'publisher_type' => in_array($r['publisher_type'] ?? '', self::PUBLISHER_TYPES, true) ? $r['publisher_type'] : 'funder',
                'checked_at' => $checked && strtotime($checked) ? $checked : now()->toDateString(), 'note' => filled($r['note'] ?? null) ? Str::limit(trim($r['note']), 250, '') : null];
        }

        return $out;
    }

    private function url(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? Str::limit($url, 495, '') : null;
    }
}
