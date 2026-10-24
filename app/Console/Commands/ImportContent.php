<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Domain\System\ContentBundle;
use Illuminate\Console\Command;

class ImportContent extends Command
{
    protected $signature = 'sas:import-content {file=data/launch/sas-content.jsonl.gz : the bundle written by sas:export-content} {--replace : empty the content tables first}';

    protected $description = 'Load the site content bundle into this database (first deploy). Refuses to run over existing content unless --replace is given.';

    public function handle(ContentBundle $bundle): int
    {
        if (! is_file($this->argument('file'))) {
            $this->error('No such file: '.$this->argument('file'));

            return self::FAILURE;
        }
        try {
            $counts = $bundle->import($this->argument('file'), (bool) $this->option('replace'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($counts as $table => $n) {
            $this->line(sprintf('  %-32s %s', $table, number_format($n)));
        }
        AuditLog::record('system.content_imported', null, null, ['rows' => array_sum($counts)], null, null);
        $this->info('Loaded '.number_format(array_sum($counts)).' rows. Now run: php artisan sas:reindex-search');

        return self::SUCCESS;
    }
}
