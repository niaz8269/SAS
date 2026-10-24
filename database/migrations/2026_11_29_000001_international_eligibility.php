<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Any subject" is explicit data, like any_nationality: a call with no subject tags and the flag off is unconfirmed, never "all".
        Schema::table('scholarship_cycles', fn (Blueprint $table) => $table->boolean('any_subject')->default(false)->after('any_nationality'));

        // Nationalities that cannot apply even though the call is otherwise open (for example a host country's own citizens).
        Schema::create('cycle_excluded_nationalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->unique(['cycle_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_excluded_nationalities');
        Schema::table('scholarship_cycles', fn (Blueprint $table) => $table->dropColumn('any_subject'));
    }
};
