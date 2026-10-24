<?php

namespace Tests\Feature\Admin;

use App\Models\CmsPage;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The sitemap cache listeners must not stop other listeners (a listener that returns false ends the chain). */
class SavedListenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_the_sitemap_cache_does_not_swallow_the_audit_trail(): void
    {
        $this->actingAs(User::factory()->create());
        Cache::put('sitemap', 'cached', 600);

        $page = CmsPage::create(['slug' => 'audit-check', 'title' => 'Audit check', 'body' => '<p>x</p>', 'published' => true]);
        Testimonial::create(['name' => 'A', 'quote' => 'q']);

        $this->assertNull(Cache::get('sitemap'), 'the sitemap cache was cleared');
        $actions = DB::table('audits')->pluck('action')->all();
        $this->assertContains('site.CmsPage_created', $actions);
        $this->assertContains('site.Testimonial_created', $actions);
        $this->assertNotNull($page);
    }
}
