<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counsellor_messages', function (Blueprint $t) {
            $t->foreignId('document_id')->nullable()->after('body')->constrained('documents')->nullOnDelete(); // a file sent with the message, kept in the student's documents
        });
    }

    public function down(): void
    {
        Schema::table('counsellor_messages', fn (Blueprint $t) => $t->dropConstrainedForeignId('document_id'));
    }
};
