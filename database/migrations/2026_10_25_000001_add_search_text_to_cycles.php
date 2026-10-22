<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept in its own narrow table so the wide text never travels with scholarship_cycles rows through sorts and listings.
        Schema::create('cycle_search_index', function (Blueprint $table) {
            $table->foreignId('cycle_id')->primary()->constrained('scholarship_cycles')->cascadeOnDelete();
            $table->text('search_text'); // maintained by App\Domain\Scholarship\SearchIndexer
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_search_index');
    }
};
