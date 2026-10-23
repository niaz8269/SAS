<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The dates of a call other than its closing date (which keeps its own versioned table): when applications open, interviews and tests, results.
        Schema::create('cycle_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('kind', 16); // opens, interview, test, results, other
            $table->string('label', 120)->nullable(); // for "other", or to name a stage ("Round 2 interviews")
            $table->string('date_type', 20)->default('not_announced'); // exact, date_only, month, range, not_announced, route_dependent
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('time_text', 80)->nullable(); // time exactly as published, never assumed ("11:59pm UK time")
            $table->string('source_text', 600)->nullable(); // the source's own wording
            $table->string('source_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['cycle_id', 'kind', 'sort']);
        });

        Schema::table('scholarship_cycles', function (Blueprint $table) {
            $table->text('institutions_note')->nullable(); // which universities take part ("any UK university offering an eligible course")
            $table->text('programmes_note')->nullable(); // which programmes or fields are offered
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_cycles', fn (Blueprint $table) => $table->dropColumn(['institutions_note', 'programmes_note']));
        Schema::dropIfExists('cycle_milestones');
    }
};
