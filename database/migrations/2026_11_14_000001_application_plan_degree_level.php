<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_plans', function (Blueprint $t) {
            $t->foreignId('degree_level_id')->nullable()->constrained('degree_levels')->nullOnDelete(); // decides which document rules apply
        });
    }

    public function down(): void
    {
        Schema::table('application_plans', fn (Blueprint $t) => $t->dropConstrainedForeignId('degree_level_id'));
    }
};
