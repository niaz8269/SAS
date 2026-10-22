<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Addresses we must stop emailing (hard bounces, spam complaints, manual blocks). OPS-06.
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique(); // stored lower-case
            $table->string('reason', 16); // bounce, complaint, manual
            $table->string('source', 48)->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();
        });

        // Provider event ids already processed: webhook replays and retries are harmless (PAY-04 pattern, INT-01).
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->string('type', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('email_suppressions');
    }
};
