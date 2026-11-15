<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Loads the details of the Reach Oxford Scholarship and the Melbourne Research Scholarship, read on 15 November 2026 from the universities' own pages
 * in a normal browser (both had been left out of the first wave because automated reading was blocked). Applied by sas:import-details,
 * which replaces only the sections a file contains, never publishes or unpublishes anything and never marks funding as verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        $files = glob(database_path('data/scholarship-details/2026-11-15/*.json')) ?: [];
        sort($files);
        if ($files !== []) {
            Artisan::call('sas:import-details', ['files' => $files]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the previous text of each section is not kept.
    }
};
