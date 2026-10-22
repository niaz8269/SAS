<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proposed changes to a LIVE cycle. They stay here, invisible to the public, until a second person approves them.
        Schema::create('cycle_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes'); // {fields: {col: value}, relations: {name: [ids]}}
            $table->string('base_hash', 64); // fingerprint of the live values when proposed; a mismatch means the live cycle moved on
            $table->string('status', 16)->default('pending'); // pending, applied, rejected, withdrawn, outdated
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->boolean('override')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_revisions');
    }
};
