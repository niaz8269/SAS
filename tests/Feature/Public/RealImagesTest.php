<?php

namespace Tests\Feature\Public;

use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\University;
use Database\Seeders\DestinationGuidesSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_bundled_photograph_is_credited_and_exists(): void
    {
        foreach (config('sas.image_credits') as $file => $credit) {
            $this->assertFileExists(public_path($file), $file);
            $this->assertNotEmpty($credit['name']);
            $this->assertStringStartsWith('https://unsplash.com/photos/', $credit['url']);
        }
    }

    public function test_all_bundled_cover_photos_have_exactly_the_same_width_and_height(): void
    {
        foreach (array_merge(glob(public_path('images/destinations/*.jpg')), glob(public_path('images/cities/*.jpg'))) as $file) {
            [$w, $h] = getimagesize($file);
            $this->assertSame([960, 540], [$w, $h], basename($file));
        }
    }

    public function test_cover_photos_are_displayed_in_one_shared_16_by_9_frame_everywhere(): void
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china', 'is_destination' => true, 'guidance_published' => true, 'featured' => true, 'summary' => 'x']);
        University::create(['name' => 'Peking University', 'slug' => 'pku', 'csc_key' => 'A', 'province' => 'Beijing', 'country_id' => $china->id]);
        foreach (['/', '/destinations', '/universities', '/destinations/china'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('aspect-[16/', $html, $url);
            $this->assertStringNotContainsString('aspect-[3/2]', $html, $url);
            $this->assertStringContainsString('aspect-video', $html, $url);
        }
    }

    public function test_universities_show_a_photo_of_their_province_or_fall_back_to_their_country(): void
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $bj = University::create(['name' => 'Peking University', 'slug' => 'pku', 'csc_key' => 'A', 'province' => 'Beijing', 'country_id' => $china->id]);
        $hu = University::create(['name' => 'Tibet University', 'slug' => 'tu', 'csc_key' => 'B', 'province' => 'Tibet', 'country_id' => $china->id]);

        $this->assertStringEndsWith('images/cities/beijing.jpg', $bj->imageUrl());
        $this->assertStringEndsWith('images/destinations/cn.jpg', $hu->imageUrl());
        $this->assertSame('Temple of Heaven, Beijing', $bj->imageCredit()['subject']);

        $this->get('/universities')->assertOk()->assertSee('images/cities/beijing.jpg', false)->assertSee('images/destinations/cn.jpg', false);
        $this->get('/universities/pku')->assertOk()->assertSee('images/cities/beijing.jpg', false)->assertDontSee('Temple of Heaven, Beijing');
    }

    public function test_a_scholarship_uses_its_destination_country_photo_unless_it_has_its_own(): void
    {
        $uk = Country::firstOrCreate(['code' => 'GB'], ['name' => 'United Kingdom', 'slug' => 'united-kingdom']);
        $programme = ScholarshipProgramme::factory()->create(['host_country_id' => $uk->id]);
        $this->assertStringEndsWith('images/destinations/gb.jpg', $programme->imageUrl());

        $programme->update(['image_path' => 'media/own.jpg']);
        $this->assertStringContainsString('media/own.jpg', $programme->fresh()->imageUrl());

        $orphan = ScholarshipProgramme::factory()->create(['host_country_id' => null]);
        $this->assertStringEndsWith('images/destinations/default.jpg', $orphan->imageUrl());
    }

    public function test_published_scholarship_cards_carry_a_photo(): void
    {
        $uk = Country::firstOrCreate(['code' => 'GB'], ['name' => 'United Kingdom', 'slug' => 'united-kingdom']);
        $cycle = ScholarshipCycle::factory()->published()->create();
        $cycle->programme->update(['host_country_id' => $uk->id]);
        $this->get('/')->assertOk();
        $this->get('/scholarships/'.$cycle->programme->slug.'/'.$cycle->slug)->assertOk()->assertSee('images/destinations/gb.jpg', false);
    }

    public function test_destination_guides_are_seeded_for_the_countries_scholarships_point_at_and_never_overwrite_staff_text(): void
    {
        $this->seed(TaxonomySeeder::class);
        Country::where('code', 'US')->update(['summary' => 'Our own words.']);
        $this->seed(DestinationGuidesSeeder::class);

        foreach (array_diff(array_keys(DestinationGuidesSeeder::GUIDES), ['US']) as $code) {
            $c = Country::where('code', $code)->first();
            $this->assertTrue($c->is_destination && $c->guidance_published, $code);
            $this->assertNotEmpty($c->guidance_sources, $code);
            $this->assertFileExists(public_path('images/destinations/'.strtolower($code).'.jpg'));
        }
        $this->assertSame('Our own words.', Country::where('code', 'US')->value('summary'));
        foreach (array_keys(DestinationGuidesSeeder::GUIDES) as $code) {
            $this->assertFileExists(public_path('images/destinations/'.strtolower($code).'.jpg'), $code);
        }
        $this->get('/destinations')->assertOk()->assertSee('Japan')->assertSee('images/destinations/jp.jpg', false);
    }
}
