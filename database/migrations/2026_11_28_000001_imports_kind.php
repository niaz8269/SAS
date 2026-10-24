<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The import tool now takes three kinds of file: scholarships (as before), universities and degree programmes.
        Schema::table('imports', fn (Blueprint $table) => $table->string('kind', 16)->default('scholarships')->after('user_id'));
    }

    public function down(): void
    {
        Schema::table('imports', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
