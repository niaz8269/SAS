<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Fills gaps found by an audit on 15 November 2026: the teaching language where it was blank or "not stated", the eligible countries where none were stored,
 * and dates (opening, interviews, results and closing dates reported for the latest call, with the reporter named in the text where the source is not the funder).
 * Applied by sas:apply-gap-fixes, which never replaces a stored value, never changes the status, a confirmed deadline or the funding type, and does nothing the second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        $files = glob(database_path('data/gap-fixes/2026-11-15/*.json')) ?: [];
        sort($files);
        if ($files !== []) {
            Artisan::call('sas:apply-gap-fixes', ['files' => $files]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the added dates carry their own source text and can be removed in the Admin.
    }
};
