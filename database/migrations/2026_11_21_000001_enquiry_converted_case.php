<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $t) {
            $t->foreignId('converted_case_id')->nullable()->after('duplicate_of_id')->constrained('service_cases')->nullOnDelete(); // the case this enquiry became
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', fn (Blueprint $t) => $t->dropConstrainedForeignId('converted_case_id'));
    }
};
