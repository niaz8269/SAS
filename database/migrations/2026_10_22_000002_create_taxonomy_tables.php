<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique(); // ISO 3166-1 alpha-2
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('demonym')->nullable();
            $table->string('region')->nullable();
            // Destination (study guidance) page content, PUB-04.
            $table->boolean('is_destination')->default(false)->index();
            $table->boolean('guidance_published')->default(false);
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->text('summary')->nullable();
            $table->longText('study_guidance')->nullable();
            $table->json('cost_notes')->nullable(); // [{label, amount_text, currency, updated_on}]
            $table->json('guidance_sources')->nullable(); // [{label, url}]
            $table->date('guidance_reviewed_at')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_credit')->nullable();
            $table->timestamps();
        });

        Schema::create('degree_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subject_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->unique(['subject_id', 'alias']);
            $table->index('alias');
        });

        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('country_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->string('domain')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('funders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 24)->default('other'); // government, university, foundation, organisation, other
            $table->foreignId('country_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('website')->nullable();
            $table->string('domain')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['funders', 'universities', 'subject_aliases', 'subjects', 'degree_levels', 'countries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
