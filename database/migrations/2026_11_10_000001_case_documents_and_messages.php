<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents a student chose to share with the people handling one case. Sharing is always the student's own choice and can be withdrawn.
        Schema::create('case_document_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('service_cases')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('consent_text', 400); // the wording the student agreed to
            $table->timestamp('shared_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['case_id', 'document_id']);
        });

        // The conversation inside a case: replies the student can see, and internal notes they cannot.
        Schema::create('case_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('service_cases')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 8)->default('public'); // public, internal
            $table->text('body');
            $table->timestamps();
            $table->index(['case_id', 'visibility']);
        });

        Schema::table('service_cases', function (Blueprint $table) {
            $table->boolean('awaiting_reply')->default(false); // the student wrote last and staff have not answered yet
        });
    }

    public function down(): void
    {
        Schema::table('service_cases', fn (Blueprint $t) => $t->dropColumn('awaiting_reply'));
        Schema::dropIfExists('case_messages');
        Schema::dropIfExists('case_document_shares');
    }
};
