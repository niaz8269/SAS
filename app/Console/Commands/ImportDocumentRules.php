<?php

namespace App\Console\Commands;

use App\Domain\Documents\RulesImporter;
use Illuminate\Console\Command;

/** Applies document rules from JSON files to the scholarships they name. Add a file for each new scholarship and run this again. */
class ImportDocumentRules extends Command
{
    protected $signature = 'sas:import-document-rules {path? : a JSON file or a folder (default data/document-rules)} {--dry-run : check the files and change nothing}';

    protected $description = 'Import document requirement rules for scholarships from JSON files (see data/document-rules/README.md).';

    public function handle(RulesImporter $importer): int
    {
        $path = $this->argument('path') ?: base_path('data/document-rules');
        $files = is_dir($path) ? glob(rtrim($path, '/\\').'/*.json') : (is_file($path) ? [$path] : []);
        if (! $files) {
            $this->error("No JSON files at $path.");

            return self::FAILURE;
        }

        $failed = 0;
        $rows = [];
        foreach ($files as $file) {
            $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($file)), true); // tolerate a UTF-8 byte order mark
            if (! is_array($data)) {
                $this->error(basename($file).': not valid JSON.');
                $failed++;

                continue;
            }
            $report = $importer->import($data, (bool) $this->option('dry-run'));
            foreach ($report['errors'] as $e) {
                $this->error(basename($file).': '.$e);
            }
            $failed += $report['errors'] ? 1 : 0;
            $rows[] = [basename($file), $report['rules'], $report['created'], $report['updated'], $report['errors'] ? 'ERROR' : 'ok'];
        }
        $this->table(['File', 'Rules', 'Created', 'Updated', 'Result'], $rows);
        $this->info($this->option('dry-run') ? 'Dry run: nothing was changed.' : 'Done.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
