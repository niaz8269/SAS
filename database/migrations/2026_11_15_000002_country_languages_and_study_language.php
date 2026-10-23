<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $t) {
            $t->json('official_languages')->nullable(); // overrides config/languages.php when set
        });
        Schema::table('application_plans', function (Blueprint $t) {
            $t->string('study_language', 60)->nullable(); // the language the student wants to study in: English or the host country's
        });
    }

    public function down(): void
    {
        Schema::table('application_plans', fn (Blueprint $t) => $t->dropColumn('study_language'));
        Schema::table('countries', fn (Blueprint $t) => $t->dropColumn('official_languages'));
    }
};
