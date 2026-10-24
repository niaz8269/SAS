<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facts about the university itself that are not in the official programme list: founding year, hospitals, recognitions, accommodation.
        Schema::table('universities', function (Blueprint $table) {
            $table->json('profile')->nullable();
            $table->date('profile_checked_at')->nullable();
        });

        // What a programme costs a student, one row per programme and intake. Only what the university or the government charges:
        // an agency's own service charges never go here. Hidden until someone has checked the row.
        Schema::create('university_fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('programme_name', 190);
            $table->string('level', 16)->default('bachelor');
            $table->string('duration_text', 190)->nullable();
            $table->decimal('duration_years', 3, 1)->nullable();
            $table->string('teaching_language', 60)->nullable();
            $table->string('intake_label', 80)->nullable(); // "September 2026"
            $table->char('currency', 3)->default('CNY');
            $table->unsignedInteger('tuition_amount')->nullable(); // first year (and every year unless tuition_amount_later is set)
            $table->unsignedInteger('tuition_amount_later')->nullable();
            $table->string('tuition_period', 8)->default('year'); // year or semester
            $table->string('tuition_note', 300)->nullable();
            $table->unsignedInteger('hostel_amount')->nullable();
            $table->unsignedInteger('hostel_amount_later')->nullable();
            $table->string('hostel_note', 300)->nullable();
            $table->unsignedInteger('application_fee_amount')->nullable();
            $table->json('other_fees')->nullable(); // [{label, amount, amount_max, when: first_year|every_year, refundable, note}]
            $table->string('hsk_note', 400)->nullable();
            $table->string('age_note', 200)->nullable();
            $table->string('marks_note', 300)->nullable();
            $table->json('required_documents')->nullable();
            $table->string('application_deadline', 200)->nullable();
            $table->string('source_type', 12)->default('university'); // university, government or supplied (a fee sheet given to SAS)
            $table->string('source_label', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->date('source_date')->nullable();
            $table->json('notes')->nullable(); // staff only
            $table->boolean('published')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['university_id', 'published']);
        });

        // A university is listed in the medical section only while it has at least one scholarship for international students.
        Schema::create('university_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('provider', 12)->default('university'); // csc, provincial, city, university
            $table->string('coverage', 500)->nullable();
            $table->string('amount_text', 300)->nullable();
            $table->text('eligibility')->nullable();
            $table->string('source_type', 12)->default('university');
            $table->string('source_label', 190)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('source_text', 600)->nullable();
            $table->date('checked_on')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['university_id', 'published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_scholarships');
        Schema::dropIfExists('university_fee_structures');
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn(['profile', 'profile_checked_at']));
    }
};
