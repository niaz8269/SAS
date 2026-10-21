<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('nationality_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('residence_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->json('preferred_destination_ids')->nullable();
            $table->foreignId('desired_degree_level_id')->nullable()->constrained('degree_levels')->nullOnDelete();
            $table->json('subject_interest_ids')->nullable();
            $table->string('prior_qualification')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('completion_year')->nullable();
            // STU-02: grades stay in their original scale; never auto-converted.
            $table->string('gpa_value', 16)->nullable();
            $table->string('gpa_scale', 48)->nullable(); // e.g. "CGPA /4.0", "Percentage", "UK classification"
            $table->string('gpa_scale_max', 16)->nullable();
            $table->json('language_evidence')->nullable(); // [{test, score}]
            $table->string('budget_note')->nullable();
            $table->unsignedSmallInteger('intake_year')->nullable();
            $table->date('date_of_birth')->nullable(); // optional until an age criterion needs it
            $table->timestamps();
        });

        Schema::create('saved_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'cycle_id']);
        });

        Schema::create('application_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('scholarship_cycles')->restrictOnDelete();
            $table->string('status', 24)->default('interested');
            $table->string('status_source', 24)->default('student_reported'); // student_reported | staff_confirmed
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_reference')->nullable();
            $table->text('evidence_note')->nullable();
            $table->string('requirements_hash', 64)->nullable();
            $table->timestamp('requirements_changed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'cycle_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('application_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('application_plans')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('cycle_requirements')->nullOnDelete();
            $table->string('title');
            $table->text('snapshot_text')->nullable(); // APP-02: snapshot of the requirement text at creation
            $table->boolean('is_custom')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('requirement_updated')->default(false);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('other');
            $table->string('title');
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_name');
            $table->string('storage_disk', 32);
            $table->string('storage_key', 96)->unique(); // opaque
            $table->unsignedBigInteger('size');
            $table->string('mime', 96);
            $table->string('checksum', 64);
            $table->string('scan_status', 16)->default('pending'); // pending, available, rejected
            $table->string('scan_detail')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
            $table->index('scan_status');
        });

        Schema::create('document_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->morphs('associable');
            $table->foreignId('associated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'associable_type', 'associable_id'], 'doc_assoc_unique');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 24); // deadline_reminders, transactional, marketing
            $table->string('channel', 12); // mail, in_app
            $table->boolean('enabled')->default(false);
            $table->string('consent_text')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('source', 48)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'category', 'channel']);
        });

        // NOT-03: durable, deduplicated reminder events.
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('dedup_key', 128)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // deadline_reminder
            $table->foreignId('cycle_id')->nullable()->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->foreignId('deadline_id')->nullable()->constrained('cycle_deadlines')->nullOnDelete();
            $table->unsignedInteger('deadline_version')->nullable();
            $table->unsignedSmallInteger('days_before')->nullable();
            $table->timestamp('send_at')->index();
            $table->string('status', 16)->default('pending'); // pending, sent, cancelled, skipped, failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'send_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->string('channel', 12);
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['notification_deliveries', 'notification_events', 'notification_preferences', 'document_associations',
            'document_versions', 'documents', 'application_tasks', 'application_plans', 'saved_scholarships', 'student_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
