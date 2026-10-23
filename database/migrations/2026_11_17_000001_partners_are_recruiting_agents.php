<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The university/funder scholarship-submission area was removed: every partner organisation is a recruiting agent.
     * That feature never went live; its table held only test submissions.
     */
    public function up(): void
    {
        DB::table('partner_organisations')->update(['kind' => 'agent']);
        Schema::dropIfExists('partner_submissions');
    }

    public function down(): void
    {
        // The submissions table is not restored.
    }
};
