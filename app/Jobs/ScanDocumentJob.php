<?php

namespace App\Jobs;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Scanner;
use App\Enums\ScanStatus;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** FILE-02. If the scanner cannot be reached the file simply stays "Pending scan" and the job retries. */
class ScanDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 120;

    public function __construct(public int $versionId)
    {
        $this->onQueue('scanning');
    }

    public function uniqueId(): string
    {
        return 'scan-'.$this->versionId;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    public function handle(DocumentService $documents, Scanner $scanner): void
    {
        $version = DocumentVersion::find($this->versionId);
        if (! $version || $version->scan_status !== ScanStatus::Pending) {
            return;
        }
        $disk = $documents->disk();
        $quarantine = $documents->quarantinePath($version->storage_key);
        $stream = $disk->readStream($quarantine);
        if (! is_resource($stream)) {
            $version->update(['scan_status' => ScanStatus::Rejected->value, 'scan_detail' => 'File missing from quarantine', 'scanned_at' => now()]);

            return;
        }

        try {
            $result = $scanner->scan($stream);
        } finally {
            fclose($stream);
        }

        if ($result['clean']) {
            $disk->move($quarantine, $documents->releasedPath($version->storage_key));
            $version->update(['scan_status' => ScanStatus::Available->value, 'scanned_at' => now(), 'scan_detail' => null]);
        } else {
            $disk->delete($quarantine);
            $version->update(['scan_status' => ScanStatus::Rejected->value, 'scanned_at' => now(), 'scan_detail' => $result['detail']]);
        }
    }

    public function failed(Throwable $e): void
    {
        report($e); // stays pending and never downloadable; surfaces in failed jobs / scan backlog monitoring
    }
}
