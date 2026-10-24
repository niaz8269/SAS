<?php

namespace App\Console\Commands;

use App\Domain\System\ContentBundle;
use Illuminate\Console\Command;

class ExportContent extends Command
{
    protected $signature = 'sas:export-content {file=data/launch/sas-content.jsonl.gz : where to write the bundle}';

    protected $description = 'Write the site content (scholarships, universities, fees, guides, homepage) to one portable file. No user data is included.';

    public function handle(ContentBundle $bundle): int
    {
        $path = $this->argument('file');
        @mkdir(dirname($path), 0775, true);
        $counts = $bundle->export($path);
        foreach ($counts as $table => $n) {
            $this->line(sprintf('  %-32s %s', $table, number_format($n)));
        }
        $this->info('Wrote '.$path.' ('.round(filesize($path) / 1024).' KB, '.number_format(array_sum($counts)).' rows).');

        return self::SUCCESS;
    }
}
