<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', fn (Blueprint $table) => $table->string('credit')->nullable()); // photographer or source, shown on /image-credits

        $now = now();
        DB::table('menu_items')->insert(['location' => 'footer_policies', 'label' => 'Image credits', 'url' => '/image-credits', 'new_tab' => false, 'visible' => true, 'sort' => 100, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/image-credits')->delete();
        Schema::table('media_files', fn (Blueprint $table) => $table->dropColumn('credit'));
    }
};
