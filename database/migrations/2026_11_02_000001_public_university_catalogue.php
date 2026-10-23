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
        Schema::table('universities', function (Blueprint $table) {
            $table->boolean('published')->default(true)->index(); // false hides the university and its programmes from the public pages
            $table->text('summary')->nullable(); // staff-written introduction, shown on the university page
            $table->string('image_path')->nullable();
        });
        Schema::table('university_programmes', function (Blueprint $table) {
            $table->boolean('published')->default(true);
        });

        // Readable web addresses for the catalogue: /universities/peking-university instead of csc-1a2b3c.
        $taken = DB::table('universities')->whereNull('csc_key')->pluck('slug')->flip()->all();
        foreach (DB::table('universities')->whereNotNull('csc_key')->orderBy('id')->get(['id', 'name', 'name_zh']) as $u) {
            $base = Str::slug($u->name) ?: 'university';
            $slug = $base;
            for ($i = 2; isset($taken[$slug]); $i++) {
                $slug = $base.'-'.$i;
            }
            $taken[$slug] = true;
            DB::table('universities')->where('id', $u->id)->update(['slug' => $slug]);
        }

        // Editable page text for the two new pages, and menu links.
        $now = now();
        foreach ([
            ['universities', 'intro', 'Universities: heading and introduction', 'Universities in China',
                'Universities listed on the official study-in-China system for international students. Programme lists, tuition and scholarship eligibility are copied from that system and change every year: always confirm with the university and the official scholarship pages.',
                'Browse universities in China that accept international students, with their programmes, tuition and teaching languages.'],
            ['universities', 'empty', 'Universities: message when none match', null, 'No universities match your search.', null],
            ['programmes', 'intro', 'Programmes: heading and introduction', 'Search programmes',
                'Find degree programmes at universities in China by subject, degree level, teaching language and province. The star marks programmes the official system lists as open to Chinese Government Scholarship applicants; check the current call before you rely on it.',
                'Search degree programmes at universities in China by subject, level, language, province and Chinese Government Scholarship eligibility.'],
            ['programmes', 'empty', 'Programmes: message when none match', null, 'No programmes match your search. Try fewer filters or a broader word.', null],
        ] as $i => [$page, $key, $label, $heading, $body, $meta]) {
            DB::table('page_blocks')->insert(['page' => $page, 'key' => $key, 'label' => $label, 'position' => 'top', 'enabled' => true, 'sort' => 100 + ($i + 1) * 10,
                'heading' => $heading, 'body' => $body, 'subheading' => $meta, 'created_at' => $now, 'updated_at' => $now]);
        }
        $after = (int) DB::table('menu_items')->where(['location' => 'header', 'label' => 'Scholarships'])->value('sort') ?: 20;
        DB::table('menu_items')->where('location', 'header')->where('sort', '>', $after)->increment('sort', 5);
        DB::table('menu_items')->insert(['location' => 'header', 'label' => 'Universities', 'url' => '/universities', 'new_tab' => false, 'visible' => true, 'sort' => $after + 5, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('menu_items')->insert(['location' => 'footer_explore', 'label' => 'Universities and programmes', 'url' => '/universities', 'new_tab' => false, 'visible' => true, 'sort' => 15, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('url', ['/universities'])->delete();
        DB::table('page_blocks')->whereIn('page', ['universities', 'programmes'])->delete();
        Schema::table('university_programmes', fn (Blueprint $table) => $table->dropColumn('published'));
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn(['published', 'summary', 'image_path']));
    }
};
