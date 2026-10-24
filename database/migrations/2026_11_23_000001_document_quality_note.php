<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $t) {
            $t->string('quality_note', 255)->nullable()->after('mime'); // a gentle warning about the file itself, for example a very small photo
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', fn (Blueprint $t) => $t->dropColumn('quality_note'));
    }
};
