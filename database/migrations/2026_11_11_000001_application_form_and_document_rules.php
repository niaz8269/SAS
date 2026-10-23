<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** [key, name, description, default requirement, sort]. Names follow the CSC application form's supporting-documents list. */
    private const KINDS = [
        ['photo', 'Passport or visa-style photo', 'A recent colour photo of your face.', 'required', 10],
        ['passport', 'Passport (home page)', 'The page with your photo and details. It must be valid.', 'required', 20],
        ['degree_certificate', 'Certificate of highest education', 'A notarised copy of your highest degree or diploma.', 'required', 30],
        ['transcript', 'Transcripts of highest education', 'A notarised copy of your marks for each year or term.', 'required', 40],
        ['study_plan', 'Study plan', 'What you plan to study or research.', 'optional', 50],
        ['recommendation', 'Reference or recommendation letter', 'A letter from a teacher or employer.', 'optional', 60],
        ['medical', 'Physical examination record for foreigners', 'The official health examination form, completed by a doctor.', 'optional', 70],
        ['chinese_language', 'Chinese language proficiency certificate (HSK)', 'Only if you hold one.', 'optional', 80],
        ['english_language', 'English language proficiency certificate', 'Only if you hold one.', 'optional', 90],
        ['language_certificate', 'Other language certificate', null, 'optional', 100],
        ['csca', 'CSCA score report', 'For bachelor\'s degree applicants.', 'optional', 110],
        ['pre_admission_letter', 'Pre-admission letter', 'A letter from the university, if you have one.', 'optional', 120],
        ['police_clearance', 'Non-criminal record report', 'If you cannot provide it, talk to your counsellor.', 'optional', 130],
        ['publications', 'Articles or papers written or published', null, 'optional', 140],
        ['portfolio', 'Art or music work samples', 'Only for fine arts and music applicants.', 'optional', 150],
        ['cv', 'CV', null, 'optional', 160],
        ['statement', 'Statement or motivation letter', null, 'optional', 170],
        ['other', 'Other attachment', null, 'optional', 900],
    ];

    public function up(): void
    {
        Schema::create('application_forms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->longText('data')->nullable(); // encrypted JSON (see ApplicationForm)
            $t->timestamps();
        });

        Schema::create('document_kinds', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();
            $t->string('name', 120);
            $t->string('description', 300)->nullable();
            $t->string('default_requirement', 12)->default('optional'); // required | optional | not_needed
            $t->unsignedSmallInteger('sort')->default(100);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('document_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kind_id')->constrained('document_kinds')->cascadeOnDelete();
            $t->foreignId('programme_id')->nullable()->constrained('scholarship_programmes')->cascadeOnDelete();
            $t->foreignId('degree_level_id')->nullable()->constrained('degree_levels')->cascadeOnDelete();
            $t->string('requirement', 12); // required | optional | not_needed
            $t->string('note', 300)->nullable();
            $t->timestamps();
            $t->index(['kind_id', 'programme_id', 'degree_level_id']);
        });

        Schema::table('service_cases', function (Blueprint $t) {
            $t->foreignId('degree_level_id')->nullable()->constrained('degree_levels')->nullOnDelete();
            $t->timestamp('form_shared_at')->nullable();
            $t->string('form_consent_text', 400)->nullable();
        });

        foreach (self::KINDS as [$key, $name, $description, $default, $sort]) {
            DB::table('document_kinds')->insertOrIgnore(['key' => $key, 'name' => $name, 'description' => $description, 'default_requirement' => $default, 'sort' => $sort, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('service_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('degree_level_id');
            $t->dropColumn(['form_shared_at', 'form_consent_text']);
        });
        Schema::dropIfExists('document_rules');
        Schema::dropIfExists('document_kinds');
        Schema::dropIfExists('application_forms');
    }
};
