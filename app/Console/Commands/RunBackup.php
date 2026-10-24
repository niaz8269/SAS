<?php

namespace App\Console\Commands;

use App\Domain\System\Backup;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'sas:backup';

    protected $description = 'Write a backup of the database and the uploaded documents to storage/app/backups (the newest 14 are kept).';

    public function handle(Backup $backup): int
    {
        try {
            $result = $backup->run();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Backup written: '.basename($result['path']).' ('.round($result['size'] / 1048576, 1).' MB, '.$result['files'].' document files).');

        return self::SUCCESS;
    }
}
