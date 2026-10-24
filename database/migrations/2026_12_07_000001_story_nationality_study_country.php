<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Country" on a story is the person's nationality; the country where they got the scholarship (or study) is a separate answer.
        Schema::table('testimonials', function (Blueprint $table) {
            $table->renameColumn('country', 'nationality');
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('study_country', 100)->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('study_country');
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->renameColumn('nationality', 'country');
        });
    }
};
