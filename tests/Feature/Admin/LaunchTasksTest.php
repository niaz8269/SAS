<?php

namespace Tests\Feature\Admin;

use App\Domain\Site\Site;
use App\Domain\System\ContentBundle;
use App\Domain\System\Health;
use App\Enums\Role;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Livewire\Livewire;
use Tests\TestCase;

/** The owner's launch tasks: legal approval, email and queue checks, and blog review. */
class LaunchTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Health::$dns = null;
        parent::tearDown();
    }

    private function actAs(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function health(string $label): array
    {
        return collect(app(Health::class)->checks())->firstWhere('label', $label);
    }

    public function test_the_super_admin_approves_the_legal_texts_and_the_draft_notice_goes_away_and_can_come_back(): void
    {
        config(['sas.legal_approved' => false, 'sas.legal_approved_env' => false]);
        $this->get('/legal/privacy')->assertOk()->assertSee('Draft for owner review');

        $super = $this->actAs(Role::SuperAdmin);
        Livewire::test(SystemHealth::class)->callAction('approveLegal')->assertNotified('Legal texts marked as approved');
        auth()->logout();
        $this->get('/legal/privacy')->assertOk()->assertDontSee('Draft for owner review');
        $this->get('/legal/terms')->assertOk()->assertDontSee('Draft for owner review');
        $this->assertDatabaseHas('audits', ['action' => 'site.legal_approved']);
        $this->assertSame($super->name, Site::get('legal_approved_by'));

        $this->actingAs($super);
        $this->assertSame('approved by '.$super->name, $this->health('Legal texts')['value']);
        Livewire::test(SystemHealth::class)->callAction('withdrawLegal');
        auth()->logout();
        $this->get('/legal/privacy')->assertSee('Draft for owner review');
    }

    public function test_only_the_super_admin_reaches_system_health_and_its_buttons(): void
    {
        $this->actAs(Role::Administrator);
        $this->assertFalse(SystemHealth::canAccess());
        Livewire::test(SystemHealth::class)->assertForbidden();
    }

    public function test_the_test_email_button_says_when_mail_only_goes_to_the_log(): void
    {
        $this->actAs(Role::SuperAdmin);
        config(['mail.default' => 'array']);
        Livewire::test(SystemHealth::class)->callAction('testEmail')->assertNotified('Written to the log only');
    }

    public function test_system_health_checks_the_spf_and_dmarc_records_of_the_sender_domain_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        config(['mail.from.address' => 'noreply@sas.cas.moe']);
        Cache::flush();
        $asked = [];
        Health::$dns = function (string $host) use (&$asked) {
            $asked[] = $host;

            return $host === 'sas.cas.moe' ? [['txt' => 'v=spf1 include:_spf.mailer.example ~all']] : [];
        };

        $this->assertSame('ok', $this->health('Email sender record (SPF)')['level']);
        $dmarc = $this->health('Email sender record (DMARC)');
        $this->assertSame('warn', $dmarc['level']);
        $this->assertStringContainsString('_dmarc', $dmarc['hint']);
        $this->assertContains('_dmarc.sas.cas.moe', $asked);

        Cache::flush();
        Health::$dns = fn (string $host) => str_starts_with($host, '_dmarc.') ? [['txt' => 'v=DMARC1; p=none']] : [['txt' => 'v=spf1 -all']];
        $this->assertSame('ok', $this->health('Email sender record (DMARC)')['level']);
    }

    public function test_no_dns_lookups_happen_outside_a_live_site(): void
    {
        Health::$dns = fn () => throw new \RuntimeException('should not look up DNS');
        $labels = collect(app(Health::class)->checks())->pluck('label');
        $this->assertFalse($labels->contains('Email sender record (SPF)'));
    }

    public function test_emails_stuck_in_the_queue_are_reported(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame('ok', $this->health('Background jobs (emails)')['level']);
        DB::table('jobs')->insert(['queue' => 'mail', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time() - 3600, 'created_at' => time() - 3600]);
        $check = $this->health('Background jobs (emails)');
        $this->assertSame('bad', $check['level']);
        $this->assertStringContainsString('cron', $check['hint']);
    }

    public function test_the_one_cron_line_also_works_the_queue_unless_switched_off(): void
    {
        $has = function (): bool {
            $this->app->singleton(Schedule::class, fn () => new Schedule);
            $this->app->forgetInstance(Schedule::class);
            ScheduleFacade::clearResolvedInstance(Schedule::class);
            require base_path('routes/console.php');

            return collect($this->app->make(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'queue:work'));
        };

        config(['queue.default' => 'database', 'sas.queue_via_cron' => true]);
        $this->assertTrue($has());
        config(['sas.queue_via_cron' => false]);
        $this->assertFalse($has());
        config(['queue.default' => 'sync', 'sas.queue_via_cron' => true]);
        $this->assertFalse($has());
    }

    public function test_an_admin_reviews_edits_and_deletes_blog_posts_and_editing_the_words_clears_the_review(): void
    {
        $admin = $this->actAs(Role::Administrator);
        $post = BlogPost::create(['title' => 'Visa rules', 'slug' => 'visa-rules', 'body' => '<p>Original</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        Livewire::test(ListBlogPosts::class)->assertTableActionVisible('markReviewed', $post)->assertTableActionHidden('markUnreviewed', $post)
            ->callTableAction('markReviewed', $post);
        $post->refresh();
        $this->assertTrue($post->isReviewed());
        $this->assertSame($admin->id, $post->reviewed_by);
        $this->assertDatabaseHas('audits', ['action' => 'blog.reviewed']);

        $post->update(['featured' => true]); // not the words: the review stays
        $this->assertTrue($post->fresh()->isReviewed());

        $post->update(['body' => '<p>Corrected figure</p>']); // the words changed: it must be read again
        $this->assertFalse($post->fresh()->isReviewed());
        $this->assertNull($post->fresh()->reviewed_by);

        Livewire::test(ListBlogPosts::class)->callTableAction('markReviewed', $post->fresh())->callTableAction('markUnreviewed', $post->fresh());
        $this->assertFalse($post->fresh()->isReviewed());

        Livewire::test(ListBlogPosts::class)->callTableAction('delete', $post);
        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    public function test_a_counsellor_cannot_touch_the_blog_and_the_review_mark_is_not_exported_with_the_content(): void
    {
        $this->actAs(Role::Advisor);
        $this->assertFalse(BlogPostResource::canViewAny());
        $this->assertContains('reviewed_by', (new \ReflectionClassConstant(ContentBundle::class, 'USER_COLUMNS'))->getValue()['blog_posts']);
    }
}
