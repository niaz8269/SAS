<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('public_id', 26)->nullable()->unique(); // opaque id for URLs
            $t->foreignId('managed_by_organisation_id')->nullable()->constrained('partner_organisations')->nullOnDelete(); // the recruiting agent who looks after this student
        });
        foreach (DB::table('users')->whereNull('public_id')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['public_id' => (string) Str::ulid()]);
        }

        Schema::table('application_plans', function (Blueprint $t) {
            $t->string('application_no', 24)->nullable()->unique();
            $t->string('stage', 16)->default('draft')->index(); // draft | submitted | returned | passed
            $t->text('review_remarks')->nullable();             // what staff asked to be fixed, or a note on passing
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        foreach (DB::table('application_plans')->whereNull('application_no')->pluck('id') as $id) {
            DB::table('application_plans')->where('id', $id)->update(['application_no' => 'SAS'.date('y').str_pad((string) $id, 8, '0', STR_PAD_LEFT)]);
        }

        Schema::create('application_document_reasons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('application_plans')->cascadeOnDelete();
            $t->foreignId('kind_id')->constrained('document_kinds')->cascadeOnDelete();
            $t->string('reason', 500); // why the student cannot provide this document yet
            $t->timestamps();
            $t->unique(['plan_id', 'kind_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_document_reasons');
        Schema::table('application_plans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('reviewed_by');
            $t->dropColumn(['application_no', 'stage', 'review_remarks', 'reviewed_at']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('managed_by_organisation_id');
            $t->dropColumn('public_id');
        });
    }
};
