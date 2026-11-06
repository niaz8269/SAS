<?php

namespace Tests\Feature\Public;

use App\Domain\Seo\Sitemap;
use App\Domain\Site\Site;
use App\Models\Article;
use App\Models\CmsPage;
use App\Models\Country;
use App\Models\ScholarshipCycle;
use App\Models\Service;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    public function test_robots_txt_comes_from_the_app_and_points_to_the_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'a static file would hide the dynamic rules');
        config(['app.url' => 'https://sas.example.org']);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: https://sas.example.org/sitemap.xml', false)
            ->assertSee('Disallow: /student', false)->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /programmes?*', false)->assertDontSee('Disallow: /scholarships/', false)
            ->assertSee('Allow: /universities?page=*', false)->assertSee('Allow: /scholarships?page=*', false);
    }

    public function test_the_sitemap_lists_public_pages_with_the_date_each_record_changed(): void
    {
        Sitemap::forget();
        $cycle = $this->publishedCycle(['slug' => '2026-intake']);
        $cycle->forceFill(['updated_at' => '2026-05-04 10:00:00'])->saveQuietly();
        Sitemap::forget();

        $body = $this->sitemapXml();
        $this->assertStringContainsString('/scholarships/'.$cycle->programme->slug.'/2026-intake</loc><lastmod>2026-05-04</lastmod>', $body);
        $this->assertStringContainsString('<loc>'.rtrim(route('home'), '/').'/</loc></url>', $body, 'fixed pages carry no invented date');
        $this->assertStringContainsString('<loc>'.route('scholarships.index').'</loc><lastmod>2026-05-04</lastmod>', $body, 'a list page is as new as the newest thing on it');
        $this->assertStringNotContainsString('/login', $body);
        $this->assertStringNotContainsString('/student', $body);
    }

    public function test_new_content_appears_in_the_sitemap_at_once_and_unpublished_content_leaves_it(): void
    {
        Sitemap::forget();
        $this->sitemapXml(); // now cached

        $article = Article::create(['slug' => 'visa-tips', 'title' => 'Visa tips', 'body' => 'x', 'status' => 'published', 'published_at' => now()->subDay()]);
        $page = CmsPage::create(['slug' => 'visa-faq', 'title' => 'Visa FAQ', 'body' => '<p>x</p>', 'published' => true]);
        $service = Service::factory()->create(['published' => true]);
        $uni = University::create(['name' => 'New Test University', 'slug' => 'new-test-university', 'published' => true, 'csc_key' => 'nk1']);
        $country = Country::factory()->destination()->create(['guidance_reviewed_at' => '2026-10-01', 'guidance_sources' => [['label' => 'Official portal', 'url' => 'https://example.gov/study']]]);
        $cycle = $this->publishedCycle(['slug' => '2027-intake']);

        $body = $this->sitemapXml();
        foreach ([
            '/resources/visa-tips', '/pages/visa-faq', '/services/'.$service->slug, '/universities/new-test-university',
            '/destinations/'.$country->slug, '/scholarships/'.$cycle->programme->slug.'/2027-intake',
        ] as $path) {
            $this->assertStringContainsString($path.'</loc>', $body, "$path should be listed as soon as it is published");
        }

        // taken down: gone again straight away
        $article->update(['status' => 'draft']);
        $page->update(['published' => false]);
        $uni->update(['published' => false]);
        $cycle->update(['publication_state' => 'draft']);
        $body = $this->sitemapXml();
        foreach (['/resources/visa-tips', '/pages/visa-faq', '/universities/new-test-university', '/scholarships/'.$cycle->programme->slug.'/2027-intake'] as $path) {
            $this->assertStringNotContainsString($path.'</loc>', $body);
        }

        // a renamed scholarship changes its address in the sitemap too
        $cycle->update(['publication_state' => 'published']);
        $cycle->programme->update(['slug' => 'renamed-scholarship']);
        $this->assertStringContainsString('/scholarships/renamed-scholarship/2027-intake</loc>', $this->sitemapXml());
    }

    public function test_a_repeated_year_in_a_scholarship_address_is_cleaned(): void
    {
        $this->assertSame('2026-intake', ScholarshipCycle::cleanKey('2026-2026-intake'));
        $this->assertSame('2026-27-call', ScholarshipCycle::cleanKey('2026-2026-27-call'));
        $this->assertSame('2026-2027-academic-year', ScholarshipCycle::cleanKey('2026-2026-2027-academic-year'));
        $this->assertSame('2026', ScholarshipCycle::cleanKey('2026'));
        $this->assertSame('2026-2027', ScholarshipCycle::cleanKey('2026-2027'));
        $this->assertSame('2025-intake', ScholarshipCycle::cleanKey('2025-intake'));

        $cycle = $this->publishedCycle(['cycle_key' => '2026-2026-intake']);
        $this->assertSame('2026 intake', $cycle->keyLabel());
        $this->assertStringEndsWith('2026 intake', $cycle->label());
        $this->assertSame('2026-27 call', $this->publishedCycle(['cycle_key' => '2026-2026-27-call', 'slug' => 'x'])->keyLabel());
    }

    public function test_an_old_scholarship_address_redirects_for_good_to_the_new_one(): void
    {
        $cycle = $this->publishedCycle(['slug' => '2026-2026-intake']);
        $programme = $cycle->programme->slug;
        $this->get("/scholarships/$programme/2026-2026-intake")->assertOk();

        $cycle->update(['slug' => '2026-intake']); // renamed in the admin or by a migration

        $this->get("/scholarships/$programme/2026-intake")->assertOk();
        $this->get("/scholarships/$programme/2026-2026-intake")->assertStatus(301)->assertRedirect(route('scholarships.show', [$programme, '2026-intake']));
        $this->get("/scholarships/$programme/never-existed")->assertNotFound();
    }

    public function test_a_draft_cycle_is_not_revealed_by_an_old_address(): void
    {
        $cycle = $this->publishedCycle(['slug' => '2026-2026-intake']);
        $programme = $cycle->programme->slug;
        $cycle->update(['slug' => '2026-intake', 'publication_state' => 'draft']);

        $this->get("/scholarships/$programme/2026-2026-intake")->assertNotFound();
    }

    public function test_pages_name_one_canonical_address_on_the_main_domain_and_share_a_preview_image(): void
    {
        config(['app.url' => 'https://sas.example.org']);

        $html = $this->get('/about')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://sas.example.org/about">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://sas.example.org/about">', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]+/images/og-default\.jpg">#', $html);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertFileExists(public_path('images/og-default.jpg'));
        [$w, $h] = getimagesize(public_path('images/og-default.jpg'));
        $this->assertSame([1200, 630], [$w, $h]);
    }

    public function test_page_titles_stay_short_and_the_home_page_has_organisation_data(): void
    {
        $cycle = $this->publishedCycle(['slug' => '2026-intake', 'intake_year' => 2026]);
        $cycle->programme->update(['title' => 'The Very Long Named International Government Scholarship for Outstanding Students Programme']);

        $html = $this->get('/scholarships/'.$cycle->programme->slug.'/2026-intake')->assertOk()->getContent();
        preg_match('#<title>(.*?)</title>#s', $html, $m);
        $this->assertLessThanOrEqual(70, mb_strlen(html_entity_decode($m[1])));
        $this->assertStringEndsWith('| SAS', html_entity_decode($m[1]));

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"Organization"', $home);
        $this->assertStringContainsString('"@type":"WebSite"', $home);
        $this->assertStringContainsString('"@context":"https://schema.org"', $home);
    }

    public function test_the_sitemap_is_an_index_with_one_file_per_kind_of_page_and_leaves_out_empty_ones(): void
    {
        Sitemap::forget();
        $uni = University::create(['name' => 'Index Test University', 'slug' => 'index-test-university', 'published' => true, 'csc_key' => 'ik1']);

        $index = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString('<loc>'.route('sitemap.section', 'pages').'</loc>', $index);
        $this->assertStringContainsString('<loc>'.route('sitemap.section', 'universities').'</loc><lastmod>'.$uni->updated_at->toDateString().'</lastmod>', $index);
        $this->assertStringNotContainsString('sitemap-scholarships.xml', $index, 'nothing is published there yet');

        foreach (['pages', 'universities'] as $section) {
            $this->assertNotFalse(simplexml_load_string($this->get("/sitemap-$section.xml")->assertOk()->getContent()), "$section is valid XML");
        }
        $this->get('/sitemap-nothing.xml')->assertNotFound();
    }

    public function test_the_main_pages_are_in_the_sitemap_and_never_a_private_or_thank_you_page(): void
    {
        University::create(['name' => 'Listed University', 'slug' => 'listed-university', 'published' => true, 'csc_key' => 'ls1']);
        Country::factory()->destination()->create(['slug' => 'listed-land']);
        $this->publishedCycle(['slug' => '2026-intake']);

        $body = $this->sitemapXml();
        foreach (['/about', '/contact', '/services', '/stories', '/share-your-story', '/be-a-partner', '/programmes', '/scholarships', '/universities', '/destinations', '/legal/privacy', '/legal/terms'] as $path) {
            $this->assertStringContainsString(rtrim(route('home'), '/').$path.'</loc>', $body, "$path is listed");
        }
        $this->assertStringNotContainsString(rtrim(route('home'), '/').'/blog</loc>', $body, 'the blog page is listed once there is a post, not before');
        foreach (['/thanks', '/login', '/register', '/admin', '/student', '/partner/', '/go/', '/enquiries'] as $path) {
            $this->assertStringNotContainsString($path, $body);
        }
    }

    public function test_numbered_pages_of_a_list_are_their_own_pages_and_filters_name_the_plain_address(): void
    {
        config(['app.url' => 'https://sas.example.org']);
        foreach (range(1, 30) as $i) {
            University::create(['name' => "Paged University $i", 'slug' => "paged-university-$i", 'published' => true, 'csc_key' => "pk$i"]);
        }

        $first = $this->get('/universities')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://sas.example.org/universities">', $first);
        $this->assertStringContainsString('<title>Universities in China for international students | SAS</title>', $first);

        $second = $this->get('/universities?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://sas.example.org/universities?page=2">', $second);
        $this->assertStringContainsString('(page 2) | SAS</title>', $second);
        $this->assertStringContainsString('Page 2.', $second);

        $filtered = $this->get('/universities?q=Paged&page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://sas.example.org/universities">', $filtered);
    }

    public function test_pages_that_may_be_indexed_say_so_and_private_pages_do_not(): void
    {
        $this->get('/about')->assertOk()->assertSee('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">', false);
        $this->get('/blog?q=visa')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('index, follow', false);
    }

    public function test_the_site_map_page_links_every_page_and_every_page_links_to_it(): void
    {
        $uni = University::create(['name' => 'Linked Test University', 'slug' => 'linked-test-university', 'published' => true, 'csc_key' => 'lk1']);
        $cycle = $this->publishedCycle(['slug' => '2026-intake']);

        $this->get('/site-map')->assertOk()->assertSee('Site map')
            ->assertSee('href="'.route('universities.show', $uni->slug).'"', false)->assertSee('Linked Test University')
            ->assertSee('href="'.route('scholarships.show', [$cycle->programme->slug, $cycle->slug]).'"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
        $this->get('/about')->assertSee('href="'.route('site-map').'"', false);

        $uni->update(['published' => false]);
        $this->get('/site-map')->assertDontSee('Linked Test University');
    }

    public function test_the_search_engine_verification_codes_from_the_admin_appear_in_every_page_head(): void
    {
        $this->get('/about')->assertDontSee('google-site-verification', false)->assertDontSee('msvalidate.01', false);

        Site::save(['google_site_verification' => 'AbC_123-xyz', 'bing_site_verification' => 'BING0123']);

        $this->get('/about')->assertSee('<meta name="google-site-verification" content="AbC_123-xyz">', false)->assertSee('<meta name="msvalidate.01" content="BING0123">', false);
        $this->get('/')->assertSee('google-site-verification', false);
    }

    public function test_hub_pages_have_breadcrumb_data_and_a_title_that_says_what_is_on_them(): void
    {
        foreach (['/about', '/contact', '/services', '/destinations', '/scholarships', '/universities', '/programmes', '/blog', '/stories', '/resources'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('"@type":"BreadcrumbList"', $html, "$path has breadcrumb data");
            preg_match('#<title>(.*?)</title>#s', $html, $m);
            $this->assertGreaterThanOrEqual(30, mb_strlen(html_entity_decode($m[1])), "$path title is not just one word");
            $this->assertLessThanOrEqual(70, mb_strlen(html_entity_decode($m[1])));
        }
    }

    public function test_destination_and_service_pages_describe_themselves_to_search_engines(): void
    {
        $country = Country::factory()->destination()->create(['name' => 'Testland', 'slug' => 'testland', 'guidance_reviewed_at' => '2026-10-01', 'summary' => 'Study in Testland is affordable and well organised.']);
        $service = Service::factory()->create(['published' => true, 'name' => 'Visa help', 'summary' => 'Checklist and form review.']);

        $html = $this->get('/destinations/testland')->assertOk()->getContent();
        $this->assertStringContainsString('<title>Study in Testland: costs and scholarships | SAS</title>', $html);
        $this->assertStringContainsString('"@type":"WebPage"', $html);
        $this->assertStringContainsString('"dateModified":"2026-10-01"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);

        $html = $this->get(route('services.show', $service))->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"Service"', $html);
        $this->assertStringContainsString('<title>Visa help for international students | SAS</title>', $html);
        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertGreaterThan(70, mb_strlen($m[1]), 'a very short service summary is lengthened for search results');
    }

    public function test_a_university_page_describes_the_university_to_search_engines(): void
    {
        $uni = University::create(['name' => 'Test Medical University', 'slug' => 'test-medical-university', 'city' => 'Xuzhou', 'province' => 'Jiangsu', 'published' => true, 'csc_key' => 'k1']);

        $html = $this->get('/universities/'.$uni->slug)->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"CollegeOrUniversity"', $html);
        $this->assertStringContainsString('"addressLocality":"Xuzhou"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }
}
