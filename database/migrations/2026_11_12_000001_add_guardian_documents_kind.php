<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_kinds')->insertOrIgnore([
            'key' => 'guardian_documents', 'name' => 'Legal guardian documents', 'description' => 'Only for applicants under 18.',
            'default_requirement' => 'optional', 'sort' => 135, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('document_kinds')->where('key', 'guardian_documents')->delete();
    }
};
