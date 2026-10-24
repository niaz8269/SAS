<?php

namespace Tests\Feature\Public;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_five_launch_posts_import_publish_and_open(): void
    {
        $this->artisan('sas:import-blog-posts')->assertSuccessful();
        $this->assertSame(5, BlogPost::count());
        $this->artisan('sas:import-blog-posts')->expectsOutputToContain('0 added')->assertSuccessful(); // running it again changes nothing

        foreach (BlogPost::with('category')->get() as $post) {
            $this->assertTrue($post->isLive(), $post->slug.' is public');
            $this->assertNotNull($post->category);
            $this->assertFileExists(public_path($post->image_path), 'the cover picture ships with the site');
            $this->assertNotEmpty($post->image_alt);
            $this->assertNotEmpty($post->image_credit);
            $this->assertStringContainsString('Last checked:', $post->body);
            $this->assertGreaterThanOrEqual(4, preg_match_all('#href="https://#', $post->body), 'official sources are linked');
            $this->assertStringNotContainsString('<script', $post->body);
            $this->assertGreaterThan(900, str_word_count(strip_tags($post->body)), 'a full explanation, not a stub');

            $this->get('/blog/'.$post->slug)->assertOk()->assertSee($post->title)->assertSee('Share');
        }

        $this->get('/blog')->assertOk()->assertSee('Chinese Government Scholarship 2027/28')->assertSee('Studying medicine in China');
    }
}
