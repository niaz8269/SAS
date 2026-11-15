<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Third round of programme lists (16 November 2026): the 131 institutions empanelled for ICCR scholarships with the scheme rules, the course types the Italian
 * government (MAECI) funds, and the rule for the AECID Africa-Med master's programme, which names no list. Where a list is only partial or only shows past awardees
 * (Brazil PEC-G, Slovakia) it is left out. Applied by sas:import-details, which replaces only the levels a file contains and never shortens a stored list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        $files = glob(database_path('data/scholarship-details/2026-11-16-majors/*.json')) ?: [];
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
