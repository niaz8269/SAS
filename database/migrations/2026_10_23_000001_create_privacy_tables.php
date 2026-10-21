<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PRIV-02: a verified request to erase an account. Holds no personal data once completed.
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('requested'); // requested, completed, cancelled, expired
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // PRIV-04: survives backups' lifetime. Contains only a keyed hash, so a restored backup can be re-erased.
        Schema::create('deletion_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->unique();
            $table->timestamp('erased_at');
            $table->timestamp('replayed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_tombstones');
        Schema::dropIfExists('deletion_requests');
    }
};
