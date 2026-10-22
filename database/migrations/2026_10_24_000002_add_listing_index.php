<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default directory and "closing soon" queries filter on state/status and sort by the next closing instant.
        Schema::table('scholarship_cycles', function (Blueprint $table) {
            $table->index(['publication_state', 'application_status', 'next_closing_sort'], 'cycles_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_cycles', function (Blueprint $table) {
            $table->dropIndex('cycles_listing_idx');
        });
    }
};
