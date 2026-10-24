<?php

namespace Tests\Feature\Public;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestinationCardsTest extends TestCase
{
    use RefreshDatabase;

    private function country(string $code, string $name, array $extra = []): Country
    {
        return Country::create(['code' => $code, 'name' => $name, 'slug' => strtolower($name), 'is_destination' => true, 'guidance_published' => true, 'featured' => true, 'summary' => "Study in $name."] + $extra);
    }

    public function test_cards_show_a_bundled_landmark_illustration_and_no_dark_gradient(): void
    {
        $this->country('CN', 'China');
        $this->country('DE', 'Germany');
        $this->country('ZZ', 'Zedland'); // a country with no bundled photo shows the default picture

        foreach (['/', '/destinations'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('images/destinations/cn.jpg', false)->assertSee('images/destinations/de.jpg', false)
                ->assertSee('images/destinations/default.jpg', false)->getContent();
            $this->assertStringNotContainsString('from-navy-700 to-navy-900', $html);
        }
    }

    public function test_a_staff_photo_replaces_the_illustration_on_cards_and_the_guide_page(): void
    {
        Storage::fake('public_media');
        $c = $this->country('CN', 'China', ['image_path' => 'media/china.jpg']);

        $this->get('/destinations')->assertSee('media/china.jpg', false)->assertDontSee('images/destinations/cn.jpg', false);
        $this->get('/destinations/china')->assertOk()->assertSee('media/china.jpg', false);
        $this->assertStringContainsString('media/china.jpg', $c->imageUrl());
    }

    public function test_every_bundled_photograph_exists(): void
    {
        foreach (['cn', 'de', 'tr', 'gb', 'default'] as $f) {
            $this->assertFileExists(public_path("images/destinations/$f.jpg"));
        }
    }

    public function test_the_consultation_banner_is_light(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('bg-navy-800 p-8 text-white', $html);
        $this->assertStringContainsString('border-gold/60 bg-white', $html);
    }
}
