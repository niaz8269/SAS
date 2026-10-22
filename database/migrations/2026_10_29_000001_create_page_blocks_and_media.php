<?php

use App\Domain\Site\SiteDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editable sections of the list pages and the contact page (built-in intro/empty/aside text plus staff-added blocks).
        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('page', 32); // directory, destinations, services, resources, contact
            $table->string('key', 40);
            $table->string('label', 80);
            $table->string('position', 8)->default('top'); // top, bottom (added blocks only; built-in blocks have a fixed place)
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->string('eyebrow')->nullable();
            $table->text('heading')->nullable();
            $table->string('subheading', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('cta2_label', 80)->nullable();
            $table->string('cta2_url', 500)->nullable();
            $table->json('items')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->unique(['page', 'key']);
            $table->index(['page', 'position', 'sort']);
        });

        // Images and PDFs staff have uploaded, reusable anywhere an image can be chosen.
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('name');
            $table->string('alt')->nullable();
            $table->string('mime', 64);
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        SiteDefaults::seedPageBlocks();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('page_blocks');
    }
};
