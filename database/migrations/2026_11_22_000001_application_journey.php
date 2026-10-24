<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_plans', function (Blueprint $t) {
            $t->string('journey_stage', 20)->default('ready')->after('stage'); // used once the application has passed review
            $t->timestamp('journey_stage_at')->nullable()->after('journey_stage');
        });

        Schema::create('journey_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained('application_plans')->cascadeOnDelete();
            $t->string('stage', 20);
            $t->string('title', 200);
            $t->date('due_on')->nullable();
            $t->boolean('custom')->default(false);
            $t->timestamp('done_at')->nullable();
            $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['plan_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_tasks');
        Schema::table('application_plans', fn (Blueprint $t) => $t->dropColumn(['journey_stage', 'journey_stage_at']));
    }
};