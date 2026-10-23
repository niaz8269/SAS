<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organisations that send SAS scholarship information: universities, funders, recruiting agents.
        Schema::create('partner_organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('kind', 16)->default('university'); // university, funder, agent, other
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('website', 300)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('status', 12)->default('active'); // active, suspended
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('partner_organisation_id')->nullable()->constrained('partner_organisations')->nullOnDelete();
        });

        // A listing a partner proposes. It never goes public by itself: editors check it and turn it into a draft that follows the normal review.
        Schema::create('partner_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained('partner_organisations')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190);
            $table->string('funder_name', 190)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete(); // where the scholarship is held
            $table->json('degree_codes')->nullable();
            $table->unsignedSmallInteger('cycle_year')->nullable();
            $table->string('intake_term', 60)->nullable();
            $table->string('funding_type', 16)->default('unknown');
            $table->text('funding_summary')->nullable();
            $table->string('teaching_language', 80)->nullable();
            $table->text('summary')->nullable();
            $table->text('eligibility')->nullable();
            $table->date('deadline_on')->nullable(); // as published, a date only; no time is assumed
            $table->string('deadline_note', 300)->nullable();
            $table->string('official_url', 500);
            $table->string('status', 16)->default('submitted'); // submitted, in_review, needs_changes, accepted, rejected
            $table->text('editor_note')->nullable(); // visible to the partner
            $table->text('internal_note')->nullable(); // staff only
            $table->foreignId('cycle_id')->nullable()->constrained('scholarship_cycles')->nullOnDelete(); // the draft created from it
            $table->timestamps();
            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_submissions');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('partner_organisation_id'));
        Schema::dropIfExists('partner_organisations');
    }
};
