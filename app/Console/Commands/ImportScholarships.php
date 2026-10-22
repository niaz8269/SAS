<?php

namespace App\Console\Commands;

use App\Domain\Editorial\ScholarshipImporter;
use App\Models\User;
use Illuminate\Console\Command;

class ImportScholarships extends Command
{
    protected $signature = 'sas:import-scholarships {file : path to a CSV in the import template format} {--commit : apply valid rows as DRAFT cycles} {--as= : email of the staff user to record as the importer}';

    protected $description = 'Stage (and optionally commit) a scholarship CSV. Rows only ever become drafts that still need review; nothing is published.';

    public function handle(ScholarshipImporter $importer): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }
        $user = $this->option('as') ? User::where('email', $this->option('as'))->first() : null;

        $import = $importer->stage($file, basename($file), $user);
        $this->info("Staged: {$import->total_rows} row(s), {$import->valid_rows} valid, {$import->error_rows} with errors.");
        foreach ($import->rows()->where('status', 'invalid')->get() as $row) {
            $this->warn("Row {$row->row_number}: ".implode(' | ', $row->errors ?? []));
        }
        if ($import->status === 'failed') {
            $this->error($import->summary['error'] ?? 'Import failed.');

            return self::FAILURE;
        }
        if ($this->option('commit')) {
            $importer->commit($import, $user);
            $this->info('Committed as drafts. Open the admin to add evidence checks, then submit for review.');
        } else {
            $this->line('Nothing written to the scholarship tables. Re-run with --commit to create the drafts.');
        }

        return self::SUCCESS;
    }
}
