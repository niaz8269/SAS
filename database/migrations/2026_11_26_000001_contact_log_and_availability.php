<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_follow_ups', function (Blueprint $t) {
            $t->string('contact_type', 20)->nullable()->after('status'); // call, meeting, whatsapp, email, note
            $t->string('next_step', 300)->nullable()->after('comment');
        });

        // Free times a counsellor offers for a call. A student books one; it then becomes a confirmed consultation.
        Schema::create('availability_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('counsellor_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('starts_at'); // UTC
            $t->unsignedSmallInteger('duration_minutes')->default(30);
            $t->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $t->timestamp('booked_at')->nullable();
            $t->timestamps();
            $t->index(['starts_at', 'booked_by']);
            $t->unique(['counsellor_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_slots');
        Schema::table('student_follow_ups', fn (Blueprint $t) => $t->dropColumn(['contact_type', 'next_step']));
    }
};