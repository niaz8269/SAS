<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_follow_ups', function (Blueprint $t) {
            $t->date('follow_up_on')->nullable()->after('comment'); // when the counsellor wants to look at this student again
        });

        // What the student is aiming for, as fields the counsellor can filter and match scholarships on.
        Schema::create('student_goals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('countries', 300)->nullable();
            $t->string('degree', 60)->nullable();
            $t->string('subject', 200)->nullable();
            $t->string('budget', 120)->nullable();
            $t->string('intake', 60)->nullable();
            $t->timestamps();
        });

        // Saved replies. owner_id null = shared by everyone who counsels; otherwise only that counsellor's own. {name} and {counsellor} are filled in when used.
        Schema::create('reply_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $t->string('title', 120);
            $t->text('body');
            $t->timestamps();
        });

        // A staff comment on one uploaded document (for example "the scan is too dark, please upload it again"). The student sees it on their documents.
        Schema::create('document_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('body', 500);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
        Schema::dropIfExists('reply_templates');
        Schema::dropIfExists('student_goals');
        Schema::table('student_follow_ups', fn (Blueprint $t) => $t->dropColumn('follow_up_on'));
    }
};
