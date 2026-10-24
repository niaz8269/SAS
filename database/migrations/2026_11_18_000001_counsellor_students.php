<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('counsellor_id')->nullable()->after('managed_by_organisation_id')->constrained('users')->nullOnDelete(); // the SAS counsellor looking after this student
        });

        // Staff-only follow-up notes: where the student stands, what they want, and the counsellor's last comment. The newest row is the current state.
        Schema::create('student_follow_ups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 30);
            $t->text('wants')->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'id']);
        });

        // The conversation between a student and their counsellor (not tied to one case). Staff-only notes live in the follow-ups instead.
        Schema::create('counsellor_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamps();
            $t->index(['student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counsellor_messages');
        Schema::dropIfExists('student_follow_ups');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('counsellor_id'));
    }
};
