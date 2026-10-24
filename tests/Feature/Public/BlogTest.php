<?php

namespace Tests\Feature\Public;

use App\Domain\System\ContentBundle;
use App\Enums\Role;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function makePost(array $attrs = []): BlogPost
    {
        static $n = 0;
        $n++;

        return BlogPost::create($attrs + ['title' => 'Post '.$n, 'slug' => 'post-'.$n, 'status' => 'published', 'published_at' => now()->subDay(), 'body' => '<p>Hello <strong>world</strong>.</p>']);
    }

    public function test_the_blog_page_lists_published_posts_only_newest_first(): void
    {
        $old = $this->makePost(['title' => 'Older post', 'published_at' => now()->subDays(5)]);
        $new = $this->makePost(['title' => 'Newer post', 'published_at' => now()->subDay()]);
        $this->makePost(['title' => 'A draft', 'status' => 'draft']);
        $this->makePost(['title' => 'Coming soon', 'published_at' => now()->addDay()]);

        $html = $this->get('/blog')->assertOk()->assertSee('Newer post')->assertSee('Older post')->assertDontSee('A draft')->assertDontSee('Coming soon')->getContent();
        $this->assertLessThan(strpos($html, 'Older post'), strpos($html, 'Newer post'));
        $this->assertStringContainsString('application/rss+xml', $html);
    }

    public function test_an_empty_blog_says_so_politely(): void
    {
        $this->get('/blog')->assertOk()->assertSee('Posts will appear here once they are published.');
    }

    public function test_a_post_page_shows_the_article_with_seo_data_and_reading_time(): void
    {
        $category = BlogCategory::create(['name' => 'Visas', 'slug' => 'visas']);
        $post = $this->makePost(['title' => 'How to prepare a visa file', 'slug' => 'visa-file', 'category_id' => $category->id, 'excerpt' => 'A short guide.', 'body' => '<h2>Step one</h2><p>'.str_repeat('word ', 450).'</p><script>alert(1)</script>', 'seo_title' => 'Visa file guide', 'image_path' => 'blog/cover.jpg', 'image_alt' => 'A passport', 'image_credit' => 'SAS']);

        $this->get('/blog/visa-file')->assertOk()
            ->assertSee('How to prepare a visa file')->assertSee('<h2>Step one</h2>', false)->assertDontSee('alert(1)', false)
            ->assertSee('3 min read', false)->assertSee('<title>Visa file guide | SAS</title>', false)
            ->assertSee('"@type":"BlogPosting"', false)->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('rel="canonical" href="'.rtrim(config('app.url'), '/').'/blog/visa-file"', false)
            ->assertSee('property="og:image" content="'.url($post->imageUrl()).'"', false)
            ->assertSee('alt="A passport"', false)->assertSee('/blog/category/visas', false);
        $this->get('/blog/does-not-exist')->assertNotFound();
    }

    public function test_drafts_and_scheduled_posts_are_hidden_from_the_public_but_staff_can_preview_them(): void
    {
        $draft = $this->makePost(['slug' => 'secret-draft', 'status' => 'draft']);
        $soon = $this->makePost(['slug' => 'scheduled-one', 'published_at' => now()->addDays(2)]);

        $this->get('/blog/secret-draft')->assertNotFound();
        $this->get('/blog/scheduled-one')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/blog/secret-draft')->assertNotFound(); // a student

        $this->staff(Role::Administrator);
        $this->get('/blog/secret-draft')->assertOk()->assertSee('This post is a draft.')->assertSee('noindex, nofollow', false)->assertDontSee('"@type":"BlogPosting"', false);
        $this->get('/blog/scheduled-one')->assertOk()->assertSee('scheduled for');

        // once the time comes the scheduled post is public by itself
        $this->travel(3)->days();
        auth()->logout();
        $this->get('/blog/scheduled-one')->assertOk()->assertDontSee('Preview.');
        $this->assertTrue($soon->fresh()->isLive());
        $this->assertFalse($draft->fresh()->isLive());
    }

    public function test_categories_search_and_the_featured_post(): void
    {
        $visa = BlogCategory::create(['name' => 'Visas', 'slug' => 'visas', 'description' => 'Everything about visas']);
        $life = BlogCategory::create(['name' => 'Campus life', 'slug' => 'campus-life']);
        BlogCategory::create(['name' => 'Empty category', 'slug' => 'empty']);
        $this->makePost(['title' => 'Visa tips', 'category_id' => $visa->id]);
        $this->makePost(['title' => 'Dormitory guide', 'category_id' => $life->id, 'body' => '<p>Rooms and roommates</p>']);
        $this->makePost(['title' => 'Our big announcement', 'featured' => true]);

        $this->get('/blog/category/visas')->assertOk()->assertSee('Everything about visas')->assertSee('Visa tips')->assertDontSee('Dormitory guide');
        $this->get('/blog')->assertSee('Campus life')->assertDontSee('Empty category'); // only categories with posts are offered
        $this->get('/blog?q=roommates')->assertOk()->assertSee('Dormitory guide')->assertDontSee('Visa tips')->assertSee('noindex', false);
        $this->get('/blog?q=zzzz')->assertOk()->assertSee('No posts match your search.');
        $html = $this->get('/blog')->getContent();
        $this->assertSame(1, substr_count($html, 'Our big announcement'), 'the featured post is shown once, large, at the top');
    }

    public function test_the_feed_and_the_sitemap_follow_the_published_posts(): void
    {
        Cache::forget('sitemap');
        Cache::forget('blog.feed');
        $this->get('/sitemap.xml')->assertOk(); // cached
        $this->get('/blog/feed.xml')->assertOk();

        $cat = BlogCategory::create(['name' => 'News', 'slug' => 'news']);
        $live = $this->makePost(['title' => 'Fees & deadlines <update>', 'slug' => 'fees-update', 'category_id' => $cat->id]);
        $draft = $this->makePost(['slug' => 'not-yet', 'status' => 'draft']);

        $feed = $this->get('/blog/feed.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<link>'.route('blog.show', 'fees-update').'</link>', $feed);
        $this->assertStringContainsString('Fees &amp; deadlines &lt;update&gt;', $feed);
        $this->assertStringNotContainsString('not-yet', $feed);
        $this->assertNotFalse(simplexml_load_string($feed), 'the feed is valid XML');

        $map = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('/blog/fees-update</loc>', $map);
        $this->assertStringContainsString('/blog/category/news</loc>', $map);
        $this->assertStringContainsString(route('blog.index').'</loc>', $map);
        $this->assertStringNotContainsString('/blog/not-yet', $map);

        $draft->update(['status' => 'published']);
        $this->assertStringContainsString('/blog/not-yet</loc>', $this->get('/sitemap.xml')->getContent());
        $live->update(['status' => 'draft']);
        $this->assertStringNotContainsString('/blog/fees-update', $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_home_page_has_a_latest_posts_strip_only_when_there_are_posts(): void
    {
        $this->get('/')->assertOk()->assertDontSee('From the blog');
        $this->makePost(['title' => 'Welcome to the blog']);
        $this->get('/')->assertOk()->assertSee('From the blog')->assertSee('Welcome to the blog');
    }

    public function test_an_administrator_writes_and_publishes_a_post_in_the_admin(): void
    {
        $admin = $this->staff(Role::Administrator);
        $this->assertTrue(BlogPostResource::canViewAny());
        $this->assertTrue(BlogCategoryResource::canViewAny());

        Livewire::test(CreateBlogPost::class)->assertSuccessful()
            ->fillForm(['title' => 'My first post', 'slug' => 'my-first-post', 'body' => '<p>Hello</p>', 'status' => 'published'])
            ->call('create')->assertHasNoFormErrors();

        $post = BlogPost::firstWhere('slug', 'my-first-post');
        $this->assertNotNull($post);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertTrue($post->isLive(), 'a published post with no date goes live now');
        $this->get('/blog/my-first-post')->assertOk()->assertSee('By')->assertSee($admin->name);

        Livewire::test(EditBlogPost::class, ['record' => $post->getRouteKey()])->assertSuccessful()
            ->fillForm(['title' => 'My first post, edited', 'status' => 'draft'])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($post->fresh()->isLive());
        $this->get('/blog/my-first-post')->assertSuccessful(); // the signed-in administrator previews it
    }

    public function test_a_picture_needs_a_description_and_a_source(): void
    {
        $this->staff(Role::SuperAdmin);
        Storage::fake('public_media');

        Livewire::test(CreateBlogPost::class)
            ->fillForm(['title' => 'With picture', 'slug' => 'with-picture', 'body' => '<p>x</p>', 'image_path' => UploadedFile::fake()->image('cover.jpg', 1200, 630)])
            ->call('create')->assertHasFormErrors(['image_alt', 'image_credit']);
    }

    public function test_people_without_blog_rights_cannot_use_the_blog_admin_and_cannot_publish_by_tampering(): void
    {
        $this->staff(Role::Advisor);
        $this->assertFalse(BlogPostResource::canViewAny());
        $this->assertFalse(BlogCategoryResource::canViewAny());

        $guarded = BlogPostResource::guard(['status' => 'published', 'published_at' => null]);
        $this->assertSame('draft', $guarded['status']);

        auth()->logout();
        $this->staff(Role::SuperAdmin);
        $this->assertSame('published', BlogPostResource::guard(['status' => 'published'])['status']);
    }

    public function test_the_blog_posts_travel_with_the_content_bundle_without_the_author(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost(['slug' => 'bundled', 'author_id' => $author->id]);
        $file = tempnam(sys_get_temp_dir(), 'sas').'.jsonl.gz';
        $counts = app(ContentBundle::class)->export($file);
        $this->assertSame(1, $counts['blog_posts']);

        DB::table('blog_posts')->delete();
        app(ContentBundle::class)->import($file, replace: true);
        @unlink($file);

        $back = BlogPost::find($post->id);
        $this->assertNotNull($back);
        $this->assertNull($back->author_id);
    }

    public function test_a_post_has_a_share_button_the_whole_card_opens_the_post_and_pictures_shipped_with_the_site_work(): void
    {
        $post = $this->makePost(['slug' => 'shareable', 'title' => 'A shareable post', 'image_path' => 'images/blog/chinese-government-scholarship-2027-28-how-to-prepare.jpg', 'image_alt' => 'The Great Wall', 'image_credit' => 'Photo: someone, Unsplash']);
        $this->assertSame(asset('images/blog/chinese-government-scholarship-2027-28-how-to-prepare.jpg'), $post->imageUrl());
        $this->assertFileExists(public_path('images/blog/chinese-government-scholarship-2027-28-how-to-prepare.jpg'));

        $page = $this->get('/blog/shareable')->assertOk();
        $page->assertSee('navigator.share', false)->assertSee('Share on WhatsApp')->assertSee('Copy link')->assertSee('Send by email')
            ->assertSee(rtrim(config('app.url'), '/').'/blog/shareable', false)
            ->assertSee('By <strong class="text-ink">the SAS editorial team</strong>', false)->assertSee('Photo: someone, Unsplash');
        $this->assertGreaterThanOrEqual(2, substr_count($page->getContent(), 'aria-label="Share this post"'), 'a share button near the title and another after the text');

        // the card is one big link to the post: the title link covers the whole card
        $this->get('/blog')->assertSee('after:absolute after:inset-0', false)->assertSee('href="'.route('blog.show', 'shareable').'"', false);
    }
}
