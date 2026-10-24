<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The conversation between a recruiting agency and SAS about one student. The student does not see it.
        Schema::create('agent_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('organisation_id')->constrained('partner_organisations')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('from_agent');
            $t->text('body');
            $t->timestamps();
            $t->index(['student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};