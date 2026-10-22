<?php

use App\Domain\Catalogue\ChinaPlaces;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // province and city become the English names (like name); the Chinese originals move to province_zh and city_zh.
        Schema::table('universities', function (Blueprint $table) {
            $table->string('province_zh', 64)->nullable();
            $table->string('city_zh', 120)->nullable();
        });

        // Universities the source listed without a place, filled from public knowledge (marked by the missing catalogue place in the source).
        $fill = ['上海中医药大学' => ['上海', '上海'], '烟台大学' => ['山东', '烟台'], '延边大学' => ['吉林', '延吉']];

        foreach (DB::table('universities')->whereNotNull('csc_key')->get(['id', 'name_zh', 'province', 'city']) as $u) {
            $provZh = $u->province;
            $cityZh = $u->city;
            if (($provZh === null || $provZh === '') && ($cityZh === null || $cityZh === '') && isset($fill[$u->name_zh])) {
                [$provZh, $cityZh] = $fill[$u->name_zh];
            }
            $places = ChinaPlaces::cities($cityZh);
            $provZh = $provZh ?: $places['province_zh'];
            DB::table('universities')->where('id', $u->id)->update([
                'province_zh' => $provZh ?: null,
                'city_zh' => $cityZh ?: null,
                'province' => ChinaPlaces::province($provZh),
                'city' => $places['city'],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('universities')->whereNotNull('province_zh')->update(['province' => DB::raw('province_zh'), 'city' => DB::raw('city_zh')]);
        Schema::table('universities', fn (Blueprint $table) => $table->dropColumn(['province_zh', 'city_zh']));
    }
};
