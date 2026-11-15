<?php

namespace App\Console\Commands;

use App\Models\University;
use Illuminate\Console\Command;

/**
 * Adds a short researched profile to universities that have none. A stored summary or website is never replaced, so a text
 * written in the Admin always wins. Running it again changes nothing.
 *
 * Entry: {slug, summary, website?}
 */
class ApplyUniversitySummaries extends Command
{
    protected $signature = 'sas:apply-university-summaries {files* : JSON files holding an array of entries} {--dry-run : report only, change nothing}';

    protected $description = 'Fill blank university summaries (and websites) from research files without replacing stored text';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;

        foreach ((array) $this->argument('files') as $file) {
            $entries = json_decode((string) file_get_contents($file), true);
            if (! is_array($entries)) {
                $this->error("Cannot read $file");

                continue;
            }
            foreach ($entries as $e) {
                $u = University::where('slug', $e['slug'] ?? '')->first();
                $summary = trim(strip_tags((string) ($e['summary'] ?? '')));
                if (! $u || $summary === '') {
                    $this->warn('Skipped: '.($e['slug'] ?? '?'));

                    continue;
                }
                $set = [];
                if (blank($u->summary)) {
                    $set['summary'] = $summary;
                }
                $site = (string) ($e['website'] ?? '');
                if (blank($u->website) && preg_match('~^https://[^\s<>"\']+$~i', $site)) {
                    $set['website'] = mb_substr($site, 0, 250);
                }
                if ($set !== []) {
                    $changed++;
                    if (! $dry) {
                        $u->forceFill($set)->save();
                    }
                }
            }
        }

        $this->info(($dry ? '[dry run] ' : '')."$changed university record(s) filled.");

        return self::SUCCESS;
    }
}
