<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ways students can pay SAS fees (online checkout, invoice by email, bank transfer, ...), managed in the admin and shown on the public pages.
        Schema::create('payment_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('kind', 16)->default('invoice'); // online, invoice, bank, other
            $table->text('instructions')->nullable(); // what the student does, shown on the public pages
            $table->string('link_url', 500)->nullable(); // for online payment: the checkout or payment link
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_options')->insert(['name' => 'Invoice by email', 'kind' => 'invoice', 'enabled' => true, 'sort' => 10, 'created_at' => $now, 'updated_at' => $now,
            'instructions' => 'We email you an invoice with the amount due and how to pay it. Your application work starts once the application fee is received.']);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_options');
    }
};
