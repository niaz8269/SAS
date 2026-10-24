<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A position set by staff by drag and drop (1 is first). Empty means "not placed": those come after the placed ones, in the usual order.
        foreach (['universities', 'scholarship_cycles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('sort_order')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['universities', 'scholarship_cycles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('sort_order');
            });
        }
    }
};
