<?php

use App\Domain\Site\SiteDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Site-wide values staff can change (brand, contact details, footer note, announcement bar, social links).
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Free-standing pages (About, Partners, legal pages and any new page) written in the admin.
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('meta_description', 300)->nullable();
            $table->longText('body')->nullable();
            $table->boolean('published')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Header and footer links.
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 32); // header, footer_explore, footer_policies
            $table->string('label', 80);
            $table->string('url', 500);
            $table->boolean('new_tab')->default(false);
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['location', 'sort']);
        });

        // Homepage sections: text, links, order and on/off.
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('label', 80);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->string('eyebrow')->nullable();
            $table->string('heading')->nullable();
            $table->string('subheading', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('cta2_label', 80)->nullable();
            $table->string('cta2_url', 500)->nullable();
            $table->json('items')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        SiteDefaults::seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('site_settings');
    }
};
