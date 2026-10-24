<?php

use App\Models\ScholarshipCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An address that used to work keeps working: it redirects to the cycle's current address.
        Schema::create('cycle_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('scholarship_programmes')->cascadeOnDelete();
            $table->string('slug', 96);
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->unique(['programme_id', 'slug']);
        });

        // `2026-2026-intake` becomes `2026-intake`, unless that address is already taken within the same scholarship.
        foreach (DB::table('scholarship_cycles')->get(['id', 'programme_id', 'slug']) as $cycle) {
            $clean = ScholarshipCycle::cleanKey($cycle->slug);
            if ($clean === $cycle->slug || DB::table('scholarship_cycles')->where('programme_id', $cycle->programme_id)->where('slug', $clean)->where('id', '!=', $cycle->id)->exists()) {
                continue;
            }
            DB::table('scholarship_cycles')->where('id', $cycle->id)->update(['slug' => $clean]);
            DB::table('cycle_slug_redirects')->insert(['programme_id' => $cycle->programme_id, 'slug' => $cycle->slug, 'cycle_id' => $cycle->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_slug_redirects');
    }
};
