<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Two homepage sections that fill the page with real figures and real universities: "stats" under the hero, "universities" after the destinations. */
    public function up(): void
    {
        $now = now();
        $this->place('stats', 'Figures', null, null, 'hero', $now);
        $this->place('universities', 'Universities in China', 'Study in China: universities', 'Campuses, cities and the programmes each university lists, copied from the official study-in-China system.', 'destinations', $now, 'Browse all universities', '/universities');
    }

    public function down(): void
    {
        DB::table('home_sections')->whereIn('key', ['stats', 'universities'])->delete();
    }

    private function place(string $key, string $label, ?string $heading, ?string $sub, string $after, $now, ?string $cta = null, ?string $ctaUrl = null): void
    {
        if (DB::table('home_sections')->where('key', $key)->exists()) {
            return;
        }
        $anchor = DB::table('home_sections')->where('key', $after)->value('sort');
        $sort = $anchor === null ? (int) DB::table('home_sections')->max('sort') + 10 : $anchor + 5;
        DB::table('home_sections')->where('sort', '>', $anchor ?? PHP_INT_MAX)->increment('sort', 10);
        DB::table('home_sections')->insert(['key' => $key, 'label' => $label, 'enabled' => true, 'sort' => $sort, 'heading' => $heading, 'subheading' => $sub,
            'cta_label' => $cta, 'cta_url' => $ctaUrl, 'created_at' => $now, 'updated_at' => $now]);
    }
};
