<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Students and graduates can send their own story from the website; it stays pending until staff approve it.
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique();
            $table->string('source', 12)->default('staff'); // staff (typed in by us) | submitted (sent by the person)
            $table->string('review_status', 12)->default('approved'); // pending | approved | rejected
            $table->string('country', 100)->nullable();
            $table->string('university', 190)->nullable();
            $table->string('scholarship', 190)->nullable();
            $table->string('gender', 10)->nullable(); // male | female | other: only chooses the sketch picture when there is no photo
            $table->text('story')->nullable();
            $table->string('submitter_email', 190)->nullable(); // never shown on the website
            $table->text('consent_text')->nullable(); // the exact wording the person agreed to
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->index(['review_status', 'published']);
        });

        if (! DB::table('menu_items')->where('url', '/stories')->exists() && DB::table('menu_items')->where('location', 'footer_explore')->exists()) {
            DB::table('menu_items')->insert(['location' => 'footer_explore', 'label' => 'Student stories', 'url' => '/stories', 'sort' => 47, 'visible' => true, 'new_tab' => false, 'created_at' => now(), 'updated_at' => now()]);
            Cache::forget('site.menus');
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/stories')->delete();
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['review_status', 'published']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'source', 'review_status', 'country', 'university', 'scholarship', 'gender', 'story', 'submitter_email', 'consent_text', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
