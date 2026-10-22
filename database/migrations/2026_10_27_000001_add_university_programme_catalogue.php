<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('universities', function (Blueprint $table) {
            $table->string('name_zh')->nullable()->index();
            $table->string('province', 64)->nullable()->index();
            $table->string('csc_key', 32)->nullable()->unique(); // the study-in-China system's own id for the institution
            $table->string('source_url', 500)->nullable();
            $table->timestamp('catalogue_checked_at')->nullable();
        });

        // Degree programmes a university lists on the official study-in-China system (facts only; no descriptive text).
        Schema::create('university_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->string('level', 16); // bachelor, master, doctorate, non_degree, associate
            $table->string('name_zh');
            $table->boolean('csc_eligible')->default(false); // marked as open to Chinese Government Scholarship applicants
            $table->decimal('duration_years', 3, 1)->nullable();
            $table->string('teaching_language', 48)->nullable();
            $table->unsignedInteger('tuition_cny')->nullable(); // per academic year, as listed
            $table->timestamps();
            $table->unique(['university_id', 'level', 'name_zh', 'teaching_language'], 'programme_identity');
            $table->index(['level', 'teaching_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_programmes');
        Schema::table('universities', function (Blueprint $table) {
            $table->dropColumn(['name_zh', 'province', 'csc_key', 'source_url', 'catalogue_checked_at']);
        });
    }
};
