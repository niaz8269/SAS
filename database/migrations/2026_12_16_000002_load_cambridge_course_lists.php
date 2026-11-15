<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Loads the lists of courses covered by the Cambridge Trust International Scholarship, the Trailblazer Scholarship, the Masters Studentship (international fees)
 * and the Gates Cambridge Scholarship, as the University of Cambridge Funding Search publishes them (read in a normal browser on 15 November 2026; the earlier
 * automated read could not open the "See full list" control). Applied by sas:import-details, which replaces only the levels a file contains and never shortens a stored list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        $files = glob(database_path('data/scholarship-details/2026-11-15-majors/*.json')) ?: [];
        sort($files);
        if ($files !== []) {
            Artisan::call('sas:import-details', ['files' => $files]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the previous lists are not kept.
    }
};
