<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real quotes only. A testimonial can be public only with recorded consent (enforced in the model, not just the form).
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120); // as the person agreed to be shown: full name, first name or initials
            $table->string('context', 190)->nullable(); // e.g. "Master's student, Tsinghua University, 2026"
            $table->text('quote');
            $table->string('photo_path')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->date('consent_date')->nullable();
            $table->string('consent_note', 500)->nullable(); // staff only: how and where consent was given
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // The homepage trust strip: short promises and partner / accreditation logos.
        Schema::create('trust_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->default('point'); // point, partner
            $table->string('title', 120);
            $table->string('text', 400)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        // Starting points that describe what the platform actually does (see the About page). Staff can edit or delete them.
        foreach ([
            ['Official sources', 'Every published call links to its official source and shows who checked it and when.'],
            ['Second-person review', 'A second person reviews each listing before it is published. Editors cannot approve their own changes.'],
            ['Honest deadlines', 'If a source gives a date but no time, we show the date only and never invent a closing time.'],
        ] as $i => [$title, $text]) {
            DB::table('trust_items')->insert(['kind' => 'point', 'title' => $title, 'text' => $text, 'enabled' => true, 'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Two new homepage sections, placed after "How the platform works".
        $after = (int) DB::table('home_sections')->where('key', 'how')->value('sort');
        $after = $after ?: 70;
        DB::table('home_sections')->where('sort', '>', $after)->increment('sort', 20);
        foreach ([['trust', 'Trust section', 'Why you can rely on the listings', 'What we do to keep listings trustworthy.', 10], ['testimonials', 'Student testimonials', 'What students say', null, 20]] as [$key, $label, $heading, $sub, $offset]) {
            DB::table('home_sections')->insert(['key' => $key, 'label' => $label, 'enabled' => true, 'sort' => $after + $offset, 'heading' => $heading, 'subheading' => $sub, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('home_sections')->whereIn('key', ['trust', 'testimonials'])->delete();
        Schema::dropIfExists('trust_items');
        Schema::dropIfExists('testimonials');
    }
};
