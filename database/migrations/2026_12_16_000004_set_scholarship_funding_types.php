<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Sets the funding type and the "covered benefits verified" flag for scholarships that were left as "other" or "full, not verified".
 * The type follows from the benefits already stored for each scholarship; each change is written to the audit log with its reason.
 * Applied by sas:apply-funding-types, which does nothing the second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('scholarship_cycles')->exists()) {
            return;
        }

        $files = glob(database_path('data/funding-types/2026-11-15/*.json')) ?: [];
        sort($files);
        if ($files !== []) {
            Artisan::call('sas:apply-funding-types', ['files' => $files]);
        }
    }

    public function down(): void
    {
        // Nothing to undo here: each change is in the audit log with the earlier value and can be reverted in the Admin.
    }
};
