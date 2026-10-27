<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agencies ask to become partners (recruiting agents) through the public "Be a Partner" form. Nothing exists until an administrator approves:
 * approval creates the agency and its first person, who then chooses their own password from an emailed link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id();
            $table->string('agency_name', 200);
            $table->string('agency_country', 100);
            $table->string('city', 120)->nullable();
            $table->string('website', 300)->nullable();
            $table->string('agency_email', 190)->nullable();
            $table->string('agency_phone', 40);
            $table->string('registration_number', 120)->nullable();
            $table->string('years_in_business', 20)->nullable();
            $table->string('students_per_year', 20)->nullable();
            $table->string('markets', 300)->nullable();
            $table->text('about')->nullable();
            $table->string('applicant_name', 120);
            $table->string('applicant_position', 120);
            $table->string('applicant_email', 190)->index();
            $table->string('applicant_phone', 40);
            $table->string('how_heard', 190)->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('partner_organisation_id')->nullable()->constrained('partner_organisations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_applications');
    }
};
