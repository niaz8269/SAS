<?php

namespace Tests\Feature\Public;

use App\Models\ScholarshipCycle;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            ->assertSee('Disallow: /programmes?*', false)->assertDontSee('Disallow: /scholarships/', false);
    }

    public function test_the_sitemap_lists_public_pages_with_the_date_each_record_changed(): void
    {
        Cache::forget('sitemap');
        $cycle = $this->publishedCycle(['slug' => '2026-intake']);
        $cycle->forceFill(['updated_at' => '2026-05-04 10:00:00'])->saveQuietly();
        Cache::forget('sitemap');

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/scholarships/'.$cycle->programme->slug.'/2026-intake</loc><lastmod>2026-05-04</lastmod>', $body);
        $this->assertStringContainsString('<loc>'.route('home').'</loc></url>', $body, 'fixed pages carry no invented date');
        $this->assertStringNotContainsString('/login', $body);
        $this->assertStringNotContainsString('/student', $body);
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

    public function test_a_university_page_describes_the_university_to_search_engines(): void
    {
        $uni = University::create(['name' => 'Test Medical University', 'slug' => 'test-medical-university', 'city' => 'Xuzhou', 'province' => 'Jiangsu', 'published' => true, 'csc_key' => 'k1']);

        $html = $this->get('/universities/'.$uni->slug)->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"CollegeOrUniversity"', $html);
        $this->assertStringContainsString('"addressLocality":"Xuzhou"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }
}
