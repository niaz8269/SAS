<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // name_en_source: official (copied from the institution or the study-in-China site), translated (machine/AI translation, still to verify), staff (typed in the admin).
        Schema::table('university_programmes', function (Blueprint $table) {
            $table->string('name_en')->nullable()->index();
            $table->string('name_en_source', 16)->nullable();
        });
        Schema::table('universities', function (Blueprint $table) {
            $table->string('name_en_source', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn('name_en_source'));
        Schema::table('university_programmes', fn (Blueprint $table) => $table->dropColumn(['name_en', 'name_en_source']));
    }
};
