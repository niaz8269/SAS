<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Loads the complete lists of majors and programmes of the published scholarships as their official sources name them (14 to 15 November 2026), part 3 of 6,
 * and says whether each scholarship limits the field of study or is open to any subject (only where the official wording says so).
 * The files are in database/data/scholarship-details/2026-11-14-majors and are applied by sas:import-details: it replaces only the levels a file contains,
 * never shortens a stored list, adds subject categories without removing any, and indexes the programmes for the scholarship search.
 * The work is split in six parts so that a time-out on the server can be resumed: parts already done are not run again.
 */
return new class extends Migration
{
    private const PART = 2;

    private const PARTS = 6;

    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return; // an empty database (a new install or a test): there is nothing to fill in, and the files are large
        }

        $files = glob(database_path('data/scholarship-details/2026-11-14-majors/*.json')) ?: [];
        sort($files);
        $mine = array_values(array_filter($files, fn ($f, $i) => $i % self::PARTS === self::PART, ARRAY_FILTER_USE_BOTH));
        if ($mine !== []) {
            Artisan::call('sas:import-details', ['files' => $mine]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the previous lists are not kept.
    }
};
