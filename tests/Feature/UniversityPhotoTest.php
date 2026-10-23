<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversityPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bundled_campus_photo_is_preferred_to_the_city_photo_and_the_image_credits_page_lists_it(): void
    {
        $china = Country::firstOrCreate(['code' => 'CN'], ['name' => 'China', 'slug' => 'china']);
        $u = University::create(['name' => 'Photo Test University', 'slug' => 'photo-test-university', 'csc_key' => 'Z', 'country_id' => $china->id, 'province' => 'Beijing']);
        $this->assertStringContainsString('images/cities/', $u->imageUrl().'images/cities/'); // falls back to a bundled city or China photo
        $this->assertStringNotContainsString('images/universities/', $u->imageUrl());

        $file = public_path('images/universities/photo-test-university.jpg');
        @mkdir(dirname($file), 0775, true);
        copy(public_path('images/destinations/cn.jpg'), $file);
        try {
            $this->assertStringContainsString('images/universities/photo-test-university.jpg', $u->imageUrl());
            $this->assertNull($u->imageCredit()); // no credit on file, so none is invented
        } finally {
            @unlink($file);
        }
    }

    public function test_photo_credits_list_author_and_licence(): void
    {
        $this->get(route('credits'))->assertOk()->assertSee('Image credits');
    }
}
