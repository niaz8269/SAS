<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Loads the complete details of the published scholarships as their official sources state them (13 November 2026): funding components, required
 * documents, the application process in steps, programmes per degree level, eligibility text and the sources read. The files are in
 * database/data/scholarship-details/2026-11-13 and are applied by sas:import-details, which replaces only the sections a file contains,
 * keeps a longer stored programme list, never publishes or unpublishes anything and never marks funding as verified.
 * Running it again changes nothing; it cannot be undone by a rollback, because the earlier text is not kept (the old values are in the database backup).
 */
return new class extends Migration
{
    public function up(): void
    {
        $files = glob(database_path('data/scholarship-details/2026-11-13/*.json')) ?: [];
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
