<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('category', 32);
            $table->string('name');
            $table->text('summary')->nullable();
            $table->text('audience')->nullable();
            $table->longText('description')->nullable();
            $table->json('deliverables')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('required_inputs')->nullable();
            $table->json('process_steps')->nullable();
            $table->string('price_basis')->nullable(); // null => "Request a quotation"; no invented prices (PUB-05)
            $table->string('turnaround_basis')->nullable();
            $table->unsignedSmallInteger('revision_limit')->nullable();
            $table->string('team', 48)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('published')->default(false);
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('scholarship_cycles')->nullOnDelete();
            $table->string('category', 32);
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->text('message');
            $table->string('contact_preference', 16)->default('email'); // email, phone
            $table->string('status', 16)->default('new');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_action')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->string('consent_text')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('enquiries')->nullOnDelete();
            $table->string('view_token_hash', 64)->nullable(); // COM-01 limited-purpose link
            $table->timestamp('view_token_expires_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'owner_id']);
            $table->index('email');
        });

        Schema::create('enquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 12)->default('public'); // public (student-visible) | internal
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        // BOOK-01: a request, never a confirmed booking until staff confirms a time.
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->date('preferred_from');
            $table->date('preferred_to')->nullable();
            $table->string('student_timezone', 64);
            $table->string('contact_method', 16)->default('email');
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('requested'); // requested, confirmed, cancelled, completed
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_start_utc')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 32)->default('guide');
            $table->text('excerpt')->nullable();
            $table->longText('body'); // sanitised HTML (ADM-03)
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft, in_review, published, archived
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_credit')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('schema_version', 8)->default('1');
            $table->string('status', 16)->default('staged'); // staged, validated, committed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_key', 128)->nullable();
            $table->json('payload');
            $table->string('status', 16)->default('valid'); // valid, invalid
            $table->string('action', 16)->nullable(); // create, update
            $table->json('errors')->nullable();
            $table->boolean('applied')->default(false);
            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['import_rows', 'imports', 'article_revisions', 'articles', 'consultations', 'enquiry_messages', 'enquiries', 'services'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
