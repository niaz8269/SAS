<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_plans', function (Blueprint $t) {
            $t->timestamp('review_requested_at')->nullable(); // the student asked SAS to review this application
            $t->foreignId('review_enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_plans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('review_enquiry_id');
            $t->dropColumn('review_requested_at');
        });
    }
};
