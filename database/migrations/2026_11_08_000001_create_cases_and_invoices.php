<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A case is one scholarship application SAS supports for a student: it carries the status, the assigned counsellor and the fees.
        Schema::create('service_cases', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the student
            $table->foreignId('cycle_id')->nullable()->constrained('scholarship_cycles')->nullOnDelete();
            $table->string('title', 200);
            $table->string('status', 20)->default('new'); // new, in_progress, submitted, accepted, rejected, withdrawn, closed
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_note')->nullable(); // staff only, never shown to the student
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('outcome_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'advisor_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('case_id')->constrained('service_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the student
            $table->string('kind', 20); // application_fee, agency_charge, other
            $table->string('description', 200);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 12)->default('issued'); // issued, paid, void
            $table->date('due_on')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payment_option_id')->nullable()->constrained('payment_options')->nullOnDelete();
            $table->string('payment_reference', 120)->nullable(); // bank reference or gateway receipt number, as the student or finance recorded it
            $table->unsignedInteger('refund_cents')->default(0);
            $table->string('refund_status', 8)->default('none'); // none, due, paid
            $table->timestamp('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_cases');
    }
};
