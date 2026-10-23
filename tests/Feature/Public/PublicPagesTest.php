<?php

namespace Tests\Feature\Public;

use App\Domain\Scholarship\ChangeDeadline;
use App\Enums\PublicationState;
use App\Models\AnalyticsEvent;
use App\Models\Article;
use App\Models\Country;
use App\Models\CycleBenefit;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipRoute;
use App\Models\Service;
use App\Models\User;
use App\Support\Html;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    private function url(ScholarshipCycle $c): string
    {
        return route('scholarships.show', [$c->programme->slug, $c->slug]);
    }

    public function test_published_detail_page_is_readable_without_login_and_never_shows_the_application_portal(): void
    {
        $cycle = $this->publishedCycle([], $this->exactDeadline());
        $this->get($this->url($cycle))->assertOk()
            ->assertSee('Get application support')->assertSee('Track this application')->assertSee('Application timeline')->assertSee('Universities and programmes')
            ->assertDontSee('Apply on official website')->assertDontSee('apply.example.org')->assertDontSee('/go/', false)
            ->assertSee('does not mean an application was submitted'); // tracking never implies submission
    }

    public function test_drafts_and_in_review_cycles_are_404_on_the_public_site(): void
    {
        foreach ([PublicationState::Draft, PublicationState::InReview, PublicationState::Approved, PublicationState::ChangesRequested] as $state) {
            $cycle = ScholarshipCycle::factory()->create(['publication_state' => $state->value]);
            $this->get($this->url($cycle))->assertNotFound();
        }
    }

    public function test_archived_cycle_is_labelled_and_links_to_the_newer_cycle(): void
    {
        $old = $this->publishedCycle(['intake_year' => 2027, 'cycle_key' => '2027', 'slug' => '2027']);
        $old->update(['publication_state' => 'archived', 'archived_at' => now()]);
        $new = ScholarshipCycle::factory()->published()->create(['programme_id' => $old->programme_id, 'intake_year' => 2028, 'cycle_key' => '2028', 'slug' => '2028']);

        $this->get($this->url($old))->assertOk()->assertSee('Archived call')->assertSee(route('scholarships.show', [$old->programme->slug, '2028']), false);
        $this->assertNotNull($new);
    }

    public function test_unknown_information_is_shown_as_not_confirmed_never_blank_or_zero(): void
    {
        $cycle = $this->publishedCycle();
        $this->get($this->url($cycle))->assertOk()->assertSee('Not confirmed')->assertSee('Funding components: Not confirmed.');
    }

    public function test_date_only_deadline_page_says_closing_time_not_confirmed_and_has_no_countdown(): void
    {
        $cycle = $this->publishedCycle([], ['date_type' => 'date_only', 'closes_on' => now()->addDays(10)->toDateString()]);
        $this->get($this->url($cycle))->assertOk()->assertSee('Closing time not confirmed')->assertDontSee('Closes in');
    }

    public function test_rolling_call_shows_no_countdown(): void
    {
        $cycle = $this->publishedCycle([], ['date_type' => 'rolling']);
        $this->get($this->url($cycle))->assertOk()->assertSee('Rolling admissions')->assertDontSee('x-init="setInterval', false);
    }

    public function test_fully_funded_label_requires_verified_benefits(): void
    {
        $verified = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => true]);
        $unverified = $this->publishedCycle(['funding_type' => 'full', 'fully_funded_verified' => false]);
        CycleBenefit::create(['cycle_id' => $verified->id, 'type' => 'tuition', 'coverage' => 'covered']);

        $this->assertSame('Fully funded', $verified->fundingLabel());
        $this->assertSame('Funding details not confirmed', $unverified->fundingLabel());
        $this->get($this->url($unverified))->assertDontSee('>Fully funded<', false);
    }

    public function test_routes_with_different_deadlines_stay_separate(): void
    {
        $cycle = $this->publishedCycle();
        $uni = ScholarshipRoute::create(['cycle_id' => $cycle->id, 'route_type' => 'university', 'name' => 'University route', 'application_url' => 'https://uni.example.edu/apply']);
        $emb = ScholarshipRoute::create(['cycle_id' => $cycle->id, 'route_type' => 'embassy', 'name' => 'Embassy route']);
        $action = app(ChangeDeadline::class);
        $action($cycle, ['date_type' => 'date_only', 'closes_on' => now()->addDays(30)->toDateString()], null, null, $uni->id);
        $action($cycle, ['date_type' => 'rolling'], null, null, $emb->id);

        $this->get($this->url($cycle->fresh()))->assertOk()->assertSee('University route')->assertSee('Embassy route')
            ->assertSee('Rolling admissions')->assertSee(now()->addDays(30)->format('j F Y'));
    }

    public function test_official_link_click_is_counted_without_personal_data_then_redirects(): void
    {
        $cycle = $this->publishedCycle();
        $route = $cycle->routes()->first();
        $this->get(route('scholarships.out', $route))->assertRedirect('https://apply.example.org/start');
        $event = AnalyticsEvent::where('name', 'official_link_click')->first();
        $this->assertSame($cycle->id, $event->cycle_id);
        $this->assertSame(['domain' => 'apply.example.org'], $event->meta);
    }

    public function test_official_link_refuses_non_http_schemes_and_unpublished_cycles(): void
    {
        $cycle = $this->publishedCycle();
        $bad = ScholarshipRoute::create(['cycle_id' => $cycle->id, 'route_type' => 'other', 'name' => 'Bad', 'application_url' => 'javascript:alert(1)']);
        $this->get(route('scholarships.out', $bad))->assertNotFound();

        $draft = ScholarshipCycle::factory()->create();
        $route = ScholarshipRoute::create(['cycle_id' => $draft->id, 'route_type' => 'other', 'name' => 'X', 'application_url' => 'https://example.org']);
        $this->get(route('scholarships.out', $route))->assertNotFound();
    }

    public function test_homepage_closing_soon_excludes_unknown_closed_and_rolling_calls(): void
    {
        $soon = $this->publishedCycle([], $this->exactDeadline('+5 days'));
        $rolling = $this->publishedCycle([], ['date_type' => 'rolling']);
        $unknown = $this->publishedCycle([], ['date_type' => 'not_announced']);
        $far = $this->publishedCycle([], $this->exactDeadline('+200 days'));
        foreach ([$soon, $rolling, $unknown, $far] as $c) {
            $c->programme->update(['title' => 'Prog '.$c->id]);
        }

        $html = $this->get('/')->assertOk()->getContent();
        $closing = substr($html, strpos($html, 'id="closing-soon"'), 6000);
        $this->assertStringContainsString('Prog '.$soon->id, $closing);
        $this->assertStringNotContainsString('Prog '.$rolling->id, $closing);
        $this->assertStringNotContainsString('Prog '.$unknown->id, $closing);
        $this->assertStringNotContainsString('Prog '.$far->id, $closing);
    }

    public function test_overdue_for_recheck_listings_are_not_promoted_on_the_homepage(): void
    {
        $stale = $this->publishedCycle(['featured' => true, 'review_due_at' => now()->subDay()], $this->exactDeadline());
        $stale->programme->update(['title' => 'Stale Featured Award']);
        $fresh = $this->publishedCycle(['featured' => true], $this->exactDeadline());
        $fresh->programme->update(['title' => 'Fresh Featured Award']);
        $this->get('/')->assertSee('Fresh Featured Award')->assertDontSee('Stale Featured Award');
        // It stays reachable and is labelled in the directory.
        $this->get($this->url($stale))->assertOk()->assertSee('Needs recheck');
    }

    public function test_destination_page_requires_published_guidance_and_shows_review_date_and_sources(): void
    {
        $hidden = Country::factory()->create(['is_destination' => true, 'guidance_published' => false]);
        $this->get(route('destinations.show', $hidden))->assertNotFound();

        $shown = Country::factory()->destination()->create(['guidance_reviewed_at' => '2026-10-01', 'guidance_sources' => [['label' => 'Official portal', 'url' => 'https://example.gov/study']]]);
        $this->get(route('destinations.show', $shown))->assertOk()->assertSee('Last reviewed 1 October 2026')->assertSee('Official portal');
    }

    public function test_service_page_without_a_price_says_request_a_quotation(): void
    {
        $service = Service::factory()->create(['price_basis' => null]);
        $this->get(route('services.show', $service))->assertOk()->assertSee('Request a quotation')->assertDontSee('$');
        $draft = Service::factory()->create(['published' => false]);
        $this->get(route('services.show', $draft))->assertNotFound();
    }

    public function test_sitemap_lists_only_public_pages_and_robots_blocks_private_areas(): void
    {
        $pub = $this->publishedCycle();
        $draft = ScholarshipCycle::factory()->create();
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString($pub->slug, $xml);
        $this->assertStringNotContainsString(route('scholarships.show', [$draft->programme->slug, $draft->slug]), $xml);
        $this->get('/robots.txt')->assertSee('Disallow: /student')->assertSee('Disallow: /admin')->assertSee('Sitemap:');
    }

    public function test_private_areas_send_noindex_and_no_store_headers(): void
    {
        $user = User::factory()->create();
        $r = $this->actingAs($user)->get('/student')->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $r->headers->get('X-Robots-Tag'));
    }

    public function test_rich_text_cannot_execute_scripts(): void
    {
        $dirty = '<p onclick="x()">Hi<script>alert(1)</script> <a href="javascript:alert(1)" onmouseover="y()">bad</a> <a href="https://ok.example">ok</a><img src=x onerror=alert(1)><iframe src="//evil"></iframe><svg onload=alert(1)></svg><style>*{}</style></p>';
        $clean = Html::clean($dirty);
        foreach (['<script', 'onclick', 'onerror', 'onmouseover', 'javascript:', '<iframe', '<svg', '<style', '<img'] as $needle) {
            $this->assertStringNotContainsString($needle, strtolower($clean));
        }
        $this->assertStringContainsString('href="https://ok.example"', $clean);
        $this->assertStringContainsString('rel="noopener nofollow ugc"', $clean);
    }

    public function test_published_article_renders_sanitised_and_unpublished_is_404(): void
    {
        $a = Article::create(['slug' => 'g', 'title' => 'Guide', 'body' => '<p>Safe</p><script>alert(1)</script>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->get(route('resources.show', $a))->assertOk()->assertSee('Safe')->assertDontSee('alert(1)', false);
        $d = Article::create(['slug' => 'd', 'title' => 'Draft', 'body' => 'x', 'status' => 'draft']);
        $this->get(route('resources.show', $d))->assertNotFound();
    }
}
