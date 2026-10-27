<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** The /partners page is gone (the "Be a Partner" tab on every page replaces it), so its footer link goes too. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->where('url', '/partners')->delete();
        Cache::forget('site.menus');
    }

    public function down(): void
    {
        // nothing to restore: the page itself no longer exists
    }
};
