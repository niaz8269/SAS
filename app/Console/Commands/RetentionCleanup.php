<?php

namespace App\Console\Commands;

use App\Domain\Documents\DocumentService;
use App\Enums\ScanStatus;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;

class RetentionCleanup extends Command
{
    protected $signature = 'sas:retention-cleanup {--dry-run}';

    protected $description = 'Apply the approved retention schedule (PRIV-03). Does nothing until the owner approves the periods.';

    public function handle(DocumentService $documents): int
    {
        if (! config('sas.retention.approved')) {
            $this->warn('Retention periods are not yet approved (config sas.retention.approved=false). Nothing deleted.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays(config('sas.retention.abandoned_upload_days'));
        $stale = DocumentVersion::where('scan_status', ScanStatus::Rejected->value)->where('updated_at', '<', $cutoff)->get();

        foreach ($stale as $version) {
            $this->line("Rejected upload {$version->id} past retention.");
            if (! $this->option('dry-run')) {
                $document = $version->document;
                $version->delete();
                if ($document && $document->versions()->count() === 0) {
                    $documents->delete($document);
                }
            }
        }

        return self::SUCCESS;
    }
}
