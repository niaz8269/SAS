<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_programmes', fn (Blueprint $table) => $table->string('image_path')->nullable()); // optional photo; blank = the destination country's photo
    }

    public function down(): void
    {
        Schema::table('scholarship_programmes', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }
};
