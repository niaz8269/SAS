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
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 96)->unique();
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | published (a published post with a future date is scheduled)
            $table->timestamp('published_at')->nullable();
            $table->boolean('featured')->default(false);
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_credit')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        // A "Blog" link in the header and the footer, and a "from the blog" strip on the home page (all editable under Website).
        $after = fn (string $location, string $url) => DB::table('menu_items')->where('location', $location)->where('url', $url)->value('sort');
        if (! DB::table('menu_items')->where('url', '/blog')->exists()) {
            foreach ([['header', '/resources', 'Blogs', 58], ['footer_explore', '/resources', 'Blogs', 45]] as [$location, $url, $label, $sort]) {
                if ($after($location, $url) !== null) {
                    DB::table('menu_items')->insert(['location' => $location, 'label' => $label, 'url' => '/blog', 'sort' => $sort, 'visible' => true, 'new_tab' => false, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        if (Schema::hasTable('home_sections') && ! DB::table('home_sections')->where('key', 'blog')->exists()) {
            $columns = Schema::getColumnListing('home_sections');
            $row = ['key' => 'blog', 'label' => 'Latest blog posts', 'heading' => 'From the blog', 'subheading' => 'News and advice for students', 'enabled' => true, 'sort' => 125, 'created_at' => now(), 'updated_at' => now(), 'cta_label' => 'All blog posts', 'cta_url' => '/blog'];
            DB::table('home_sections')->insert(array_intersect_key($row, array_flip($columns)));
        }
        Cache::forget('site.menus'); // the header and footer menus are cached
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/blog')->delete();
        DB::table('home_sections')->where('key', 'blog')->delete();
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
