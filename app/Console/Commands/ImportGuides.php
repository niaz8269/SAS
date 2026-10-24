<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Domain\Editorial\ScholarshipImporter;
use App\Models\Country;
use App\Models\User;
use App\Support\Html;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Loads researched "Study in <country>" guides into the destination countries: an introduction, the guide text, official costs, the country's
 * official languages and the official pages it rests on. A guide needs at least two valid official links to be stored. The text is cleaned by
 * the same sanitiser the editor uses. Whether a guide is public is never changed here: a country that has no public guide stays hidden until
 * someone reviews it and switches it on.
 */
class ImportGuides extends Command
{
    protected $signature = 'sas:import-guides {files* : JSON files (arrays of guides)} {--by= : staff email recorded as the actor} {--report= : write a Markdown report to this path} {--dry-run : report only, change nothing}';

    protected $description = 'Import researched study-destination guides (text, costs, languages, official sources).';

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
                if (! empty($e['code'])) {
                    $entries[strtoupper($e['code'])] = $e;
                }
            }
        }
        $dry = (bool) $this->option('dry-run');
        $actor = $this->option('by') ? User::where('email', $this->option('by'))->first() : null;
        $importer = new ScholarshipImporter;
        $report = [];
        $done = $skipped = 0;

        foreach ($entries as $code => $e) {
            $country = Country::where('code', $code)->first();
            if (! $country || ! $country->is_destination) {
                $report[$code] = ['Not a destination country; skipped.'];
                $skipped++;

                continue;
            }
            $notes = [];
            $sources = [];
            foreach ($e['sources'] ?? [] as $s) {
                if (! empty($s['url']) && $importer->safeUrl($s['url']) && str_starts_with($s['url'], 'https://')) {
                    $sources[$s['url']] = ['label' => Str::limit(trim((string) ($s['label'] ?? $s['url'])), 190, ''), 'url' => $s['url']];
                } else {
                    $notes[] = 'A source without a public https address was dropped.';
                }
            }
            $text = Html::clean((string) ($e['study_guidance'] ?? ''));
            if (count($sources) < 2 || mb_strlen(strip_tags($text)) < 200) {
                $report[$code] = array_merge(['Not stored: a guide needs at least two official links and a text of some length.'], $notes);
                $skipped++;

                continue;
            }
            foreach ($country->guidance_sources ?? [] as $old) { // official links already recorded stay
                if (! empty($old['url']) && ! isset($sources[$old['url']])) {
                    $sources[$old['url']] = $old;
                }
            }
            $checked = ! empty($e['checked_on']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['checked_on']) && $e['checked_on'] <= now()->toDateString() ? $e['checked_on'] : now()->toDateString();

            $costs = [];
            foreach (array_slice($e['cost_notes'] ?? [], 0, 8) as $c) {
                if (blank($c['label'] ?? null) || blank($c['amount_text'] ?? null)) {
                    continue;
                }
                $currency = preg_match('/^[A-Za-z]{3}$/', (string) ($c['currency'] ?? '')) ? strtoupper($c['currency']) : null;
                $when = ! empty($c['updated_on']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $c['updated_on']) ? $c['updated_on'] : $checked;
                $costs[] = ['label' => Str::limit(trim($c['label']), 190, ''), 'amount_text' => Str::limit(trim($c['amount_text']), 120, ''), 'currency' => $currency, 'updated_on' => $when];
            }

            $values = ['study_guidance' => $text, 'guidance_sources' => array_values($sources), 'guidance_reviewed_at' => CarbonImmutable::parse($checked)];
            if (! blank($e['summary'] ?? null)) {
                $values['summary'] = Str::limit(trim($e['summary']), 500, '');
            }
            $costs && $values['cost_notes'] = $costs;

            if (! $dry) {
                $country->forceFill($values)->save();
                AuditLog::record('country.guide_imported', $country, null, ['sources' => count($sources)], null, $actor);
            }
            $done++;
            $report[$code] = array_merge(["Guide stored: {$country->name}, ".count($sources).' sources, '.count($costs).' cost lines'.($country->guidance_published ? ' (public)' : ' (not public: review first)')], $notes, $e['notes'] ?? []);
        }

        $this->info("{$done} guides stored, {$skipped} skipped.");
        if ($path = $this->option('report')) {
            $md = "# Study guides\n\n".($dry ? "_Dry run: nothing was changed._\n\n" : '');
            foreach ($report as $code => $lines) {
                $md .= "## {$code}\n\n";
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
}
