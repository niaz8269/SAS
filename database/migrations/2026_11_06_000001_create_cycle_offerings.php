<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a scholarship offers at each degree level: one summary line per level (fields, duration, language) and, where the
        // official sources name them, the specific degree programmes or majors.
        Schema::create('cycle_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('degree_level_id')->constrained('degree_levels')->cascadeOnDelete();
            $table->string('name', 200)->nullable(); // programme or major; empty on the summary line for the level
            $table->string('institution', 200)->nullable();
            $table->text('detail')->nullable(); // for example "2 to 3 years, taught in English, any discipline"
            $table->string('source_text', 600)->nullable(); // the source's own wording
            $table->string('source_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['cycle_id', 'degree_level_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_offerings');
    }
};
