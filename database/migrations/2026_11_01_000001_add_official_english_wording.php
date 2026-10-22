<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The wording the official study-in-China site uses in English, kept beside our own name for comparison (it has typos, so it is not copied over automatically).
        Schema::table('universities', fn (Blueprint $table) => $table->string('name_en_official')->nullable());
        Schema::table('university_programmes', fn (Blueprint $table) => $table->string('name_en_official')->nullable());
    }

    public function down(): void
    {
        Schema::table('university_programmes', fn (Blueprint $table) => $table->dropColumn('name_en_official'));
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn('name_en_official'));
    }
};
