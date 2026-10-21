<?php

namespace App\Console\Commands;

use App\Domain\Identity\AccountPrivacy;
use Illuminate\Console\Command;

class ReplayDeletions extends Command
{
    protected $signature = 'sas:replay-deletions';

    protected $description = 'Re-erase accounts that were deleted after the backup being restored (PRIV-04). Run after every restore.';

    public function handle(AccountPrivacy $privacy): int
    {
        $this->info('Erased again: '.$privacy->replay().' account(s).');

        return self::SUCCESS;
    }
}
