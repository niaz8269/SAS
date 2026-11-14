<?php

use App\Domain\Scholarship\SearchIndexer;
use Illuminate\Database\Migrations\Migration;

/**
 * The keyword search of scholarships now also covers the programmes and majors each scholarship offers (and a longer text), so every scholarship is
 * indexed again once. The nightly `sas:reindex-search` keeps it current afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(SearchIndexer::class)->reindexAll();
    }

    public function down(): void
    {
        // The index is rebuilt from the data; nothing to undo.
    }
};
