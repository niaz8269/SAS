<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Heartbeat extends Command
{
    protected $signature = 'sas:heartbeat';

    protected $description = 'Records that the scheduler is running, so the System health page can tell when it stops.';

    public function handle(): int
    {
        Cache::forever('sas.heartbeat', now()->toIso8601String());

        return self::SUCCESS;
    }
}
