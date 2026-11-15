<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Second round of gap research (15 November 2026), where official pages were blocked or gave only regions, so unofficial sites were also used and
 * every text was rewritten: eligible-country lists and dates (sas:apply-gap-fixes, which never replaces a stored value), lists of programmes and
 * institutions (sas:import-details, which never shortens a stored list) and short profiles for universities that had none (sas:apply-university-summaries).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        foreach ([
            ['sas:apply-gap-fixes', 'data/gap-fixes/2026-11-15b/*.json'],
            ['sas:import-details', 'data/scholarship-details/2026-11-15-majors-b/*.json'],
            ['sas:apply-university-summaries', 'data/university-summaries/2026-11-15/*.json'],
        ] as [$command, $pattern]) {
            $files = glob(database_path($pattern)) ?: [];
            sort($files);
            if ($files !== []) {
                Artisan::call($command, ['files' => $files]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the added lists and dates carry their own sources and can be edited in the Admin.
    }
};
