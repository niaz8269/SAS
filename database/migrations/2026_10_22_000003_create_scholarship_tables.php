<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATA-01: the enduring programme is separate from each annual / intake cycle.
        Schema::create('scholarship_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('funder_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('host_country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('title');
        });

        Schema::create('programme_university', function (Blueprint $table) {
            $table->foreignId('programme_id')->constrained('scholarship_programmes')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->primary(['programme_id', 'university_id']);
        });

        Schema::create('scholarship_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('scholarship_programmes')->restrictOnDelete();
            $table->string('cycle_key', 64); // e.g. "2027" or "2027-autumn"; unique per programme
            $table->string('slug', 96);
            $table->unsignedSmallInteger('intake_year');
            $table->string('intake_term', 32)->nullable();
            $table->string('external_key', 128)->nullable()->unique(); // IMP-03 idempotency

            // DATA-03: publication workflow is separate from application availability.
            $table->string('publication_state', 24)->default('draft');
            $table->string('application_status', 24)->default('deadline_unconfirmed');
            // Denormalised sort/close instant maintained by DeadlineService (never displayed as a closing time).
            $table->timestamp('next_closing_sort')->nullable()->index();

            $table->string('funding_type', 24)->default('unknown');
            $table->text('funding_summary')->nullable();
            $table->boolean('fully_funded_verified')->default(false);
            $table->string('teaching_language', 64)->nullable();
            $table->string('delivery_mode', 16)->default('unknown');
            $table->boolean('any_nationality')->default(false); // explicit "Any nationality" (DIR-01)
            $table->text('summary')->nullable();
            $table->text('eligibility_notes')->nullable();
            $table->text('age_experience_notes')->nullable();

            $table->boolean('featured')->default(false);
            $table->boolean('sponsored')->default(false);
            $table->string('sponsored_label')->nullable();

            // SCHEMA-01
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('evidence_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['programme_id', 'cycle_key']);
            $table->unique(['programme_id', 'slug']);
            $table->index(['publication_state', 'application_status']);
            $table->index(['publication_state', 'review_due_at']);
            $table->index(['featured', 'publication_state']);
            $table->index('intake_year');
        });

        Schema::create('scholarship_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('route_type', 24)->default('funder_portal'); // university, embassy, funder_portal, departmental, other
            $table->string('name');
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->text('application_url')->nullable();
            $table->string('application_domain')->nullable();
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->char('fee_currency', 3)->nullable();
            $table->string('fee_notes')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['cycle_id', 'name']);
        });

        Schema::create('cycle_destinations', function (Blueprint $table) {
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->primary(['cycle_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('cycle_nationalities', function (Blueprint $table) {
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->primary(['cycle_id', 'country_id']);
            $table->index('country_id');
        });

        Schema::create('cycle_degree_levels', function (Blueprint $table) {
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('degree_level_id')->constrained()->restrictOnDelete();
            $table->primary(['cycle_id', 'degree_level_id']);
            $table->index('degree_level_id');
        });

        Schema::create('cycle_subjects', function (Blueprint $table) {
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->primary(['cycle_id', 'subject_id']);
            $table->index('subject_id');
        });

        Schema::create('cycle_universities', function (Blueprint $table) {
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->restrictOnDelete();
            $table->primary(['cycle_id', 'university_id']);
        });

        Schema::create('cycle_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('type', 32); // tuition, living_allowance, accommodation, travel, insurance, other
            $table->string('coverage', 16)->default('unknown'); // covered, partial, not_covered, unknown
            $table->string('limit_text')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('conditions')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('cycle_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('document_type', 48)->default('other');
            $table->text('text');
            $table->text('conditional_rule')->nullable();
            $table->boolean('mandatory')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // SCHEMA-03: structured, evidenced criteria. Anything unsafe to structure stays as text.
        Schema::create('cycle_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->string('type', 24); // min_gpa, language, age
            $table->string('operator', 8); // >=, <=, =
            $table->string('value', 32);
            $table->string('unit', 32)->nullable(); // test name for language; scale for min_gpa
            $table->boolean('mandatory')->default(true);
            $table->text('evidence')->nullable();
            $table->timestamps();
            $table->index(['cycle_id', 'type']);
        });

        // DEAD-01 / DEAD-03: versioned deadline records.
        Schema::create('cycle_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('scholarship_routes')->nullOnDelete();
            $table->string('date_type', 24); // exact, date_only, rolling, not_announced, route_dependent
            $table->string('precision', 16)->default('unknown'); // minute, day, none
            $table->text('source_text')->nullable();
            $table->string('source_timezone', 64)->nullable(); // IANA identifier
            $table->timestamp('opens_at_utc')->nullable();
            $table->timestamp('closes_at_utc')->nullable(); // only for exact deadlines
            $table->date('closes_on')->nullable(); // calendar date for date_only deadlines
            $table->date('opens_on')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->timestamps();
            $table->index(['cycle_id', 'superseded_at']);
            $table->index(['closes_at_utc', 'superseded_at']);
            $table->index(['closes_on', 'superseded_at']);
        });

        Schema::create('source_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->text('url');
            $table->string('publisher');
            $table->string('publisher_type', 24)->default('funder'); // funder, government, embassy, university, aggregator
            $table->timestamp('checked_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cycle_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_state', 24);
            $table->string('to_state', 24);
            $table->text('notes')->nullable();
            $table->boolean('override')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('correction_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->nullable()->constrained('scholarship_cycles')->nullOnDelete();
            $table->string('page_url')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->text('message');
            $table->string('status', 16)->default('new'); // new, reviewing, resolved, rejected
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['correction_reports', 'cycle_reviews', 'source_evidence', 'cycle_deadlines', 'cycle_criteria', 'cycle_requirements',
            'cycle_benefits', 'cycle_universities', 'cycle_subjects', 'cycle_degree_levels', 'cycle_nationalities', 'cycle_destinations',
            'scholarship_routes', 'scholarship_cycles', 'programme_university', 'scholarship_programmes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
