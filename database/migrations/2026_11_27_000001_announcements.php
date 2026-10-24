<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->text('body');
            $t->string('audience', 20); // all, programme, deadline, agent, counsellor, journey
            $t->string('audience_value', 40)->nullable(); // the scholarship, number of days, agency, counsellor or journey stage the audience is built from
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('recipients')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
