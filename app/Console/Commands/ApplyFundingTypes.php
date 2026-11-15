<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Domain\Scholarship\SearchIndexer;
use App\Models\ScholarshipCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sets the funding type, and whether the covered benefits are verified, from a reviewed file. The file lists each scholarship
 * with the reason; the type follows from the benefits stored for it (read from the funder's pages). A scholarship is marked
 * verified ("Fully funded" may be shown) only when tuition and a living allowance are both stored as covered for every awardee.
 * Each change is written to the audit log with its reason. Running it again changes nothing. It never touches a cycle whose
 * stored value already matches, and never changes a scholarship that is not in the file.
 *
 * Entry: {external_key, funding_type: full|partial|tuition_only|stipend_only|other, fully_funded_verified: bool, reason: string}
 */
class ApplyFundingTypes extends Command
{
    protected $signature = 'sas:apply-funding-types {files* : JSON files holding an array of entries} {--dry-run : report only, change nothing}';

    protected $description = 'Set funding types and the verified flag from a reviewed file, with an audit-log entry for each change';

    private const TYPES = ['full', 'partial', 'tuition_only', 'stipend_only', 'other'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;
        $same = 0;
        $bad = 0;

        foreach ((array) $this->argument('files') as $file) {
            $entries = json_decode((string) file_get_contents($file), true);
            if (! is_array($entries)) {
                $this->error("Cannot read $file");

                continue;
            }
            foreach ($entries as $e) {
                $cycle = ScholarshipCycle::where('external_key', $e['external_key'] ?? '')->first();
                $type = (string) ($e['funding_type'] ?? '');
                $verified = (bool) ($e['fully_funded_verified'] ?? false);
                if (! $cycle || ! in_array($type, self::TYPES, true) || blank($e['reason'] ?? null) || ($verified && $type !== 'full')) {
                    $bad++;
                    $this->warn('Skipped: '.($e['external_key'] ?? '?'));

                    continue;
                }
                if ($cycle->funding_type === $type && (bool) $cycle->fully_funded_verified === $verified) {
                    $same++;

                    continue;
                }
                $changed++;
                if ($dry) {
                    continue;
                }
                DB::transaction(function () use ($cycle, $type, $verified, $e) {
                    $before = ['funding_type' => $cycle->funding_type, 'fully_funded_verified' => (bool) $cycle->fully_funded_verified];
                    $cycle->forceFill(['funding_type' => $type, 'fully_funded_verified' => $verified])->save();
                    AuditLog::record('cycle.funding_type_set', $cycle, $before, ['funding_type' => $type, 'fully_funded_verified' => $verified], trim((string) $e['reason']));
                    app(SearchIndexer::class)->reindex($cycle->fresh());
                });
            }
        }

        $this->info(($dry ? '[dry run] ' : '')."$changed scholarship(s) changed, $same already as listed, $bad skipped.");

        return self::SUCCESS;
    }
}
