<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The process as the official source describes it, in order: how to apply, how applicants are selected, what happens after selection.
        Schema::create('cycle_application_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('phase', 16); // apply, selection, after_award
            $table->string('title', 200);
            $table->text('detail')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['cycle_id', 'phase', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_application_steps');
    }
};
