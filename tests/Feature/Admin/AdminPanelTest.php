<?php

namespace Tests\Feature\Admin;

use App\Domain\Editorial\CycleWorkflow;
use App\Enums\Role;
use App\Filament\Resources\Articles\Pages\ManageArticles;
use App\Filament\Resources\Consultations\Pages\ManageConsultations;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\ScholarshipCycles\Pages\CreateScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\Pages\EditScholarshipCycle;
use App\Filament\Resources\ScholarshipCycles\Pages\ListScholarshipCycles;
use App\Filament\Resources\ScholarshipCycles\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Article;
use App\Models\Consultation;
use App\Models\Enquiry;
use App\Models\ScholarshipCycle;
use App\Models\ScholarshipProgramme;
use App\Models\SourceEvidence;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\BuildsScholarships;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsScholarships, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    /** A staff member who has already completed multi-factor set-up. */
    private function staff(Role|string ...$roles): User
    {
        $user = User::factory()->role(...$roles)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function draftWithSource(User $creator): ScholarshipCycle
    {
        $cycle = ScholarshipCycle::factory()->create(['created_by' => $creator->id]);
        SourceEvidence::create(['cycle_id' => $cycle->id, 'url' => 'https://example.org', 'publisher' => 'Funder', 'publisher_type' => 'funder', 'checked_at' => now(), 'created_by' => $creator->id]);

        return $cycle;
    }

    public function test_students_and_guests_cannot_enter_the_staff_panel(): void
    {
        $this->get('/admin')->assertRedirect(); // to the Filament login
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->role(Role::PartnerManager)->create())->get('/admin')->assertForbidden();
    }

    public function test_staff_are_not_asked_for_an_authenticator_while_two_step_is_switched_off(): void
    {
        $editor = User::factory()->role('editor')->create();
        $this->actingAs($editor)->get('/admin')->assertOk();
        $this->get('/admin/scholarship-cycles')->assertOk();
    }

    public function test_suspended_staff_lose_panel_access(): void
    {
        $user = $this->staff(Role::SuperAdmin);
        $this->get('/admin')->assertOk();
        $user->suspend();
        $this->get('/admin')->assertForbidden();
    }

    public function test_navigation_access_matches_each_role(): void
    {
        $matrix = [
            ['editor', ['email-suppressions' => 403, 'scholarship-cycles' => 200, 'imports' => 200, 'articles' => 200, 'users' => 403, 'audits' => 403, 'enquiries' => 403, 'services' => 403]],
            ['reviewer', ['scholarship-cycles' => 200, 'imports' => 403, 'articles' => 200, 'users' => 403, 'audits' => 403]],
            [Role::Advisor, ['enquiries' => 200, 'consultations' => 200, 'students' => 200, 'scholarship-cycles' => 403, 'users' => 403, 'imports' => 403, 'services' => 403, 'invoices' => 200, 'audits' => 403, 'counsellor-report' => 403]],
            [Role::Administrator, ['users' => 200, 'audits' => 200, 'email-suppressions' => 200, 'enquiries' => 200, 'consultations' => 200, 'services' => 200, 'scholarship-cycles' => 200, 'imports' => 200, 'articles' => 200, 'students' => 200, 'invoices' => 200]],
            [Role::SuperAdmin, ['users' => 200, 'audits' => 200, 'enquiries' => 200, 'services' => 200, 'scholarship-cycles' => 200]],
        ];
        foreach ($matrix as [$role, $paths]) {
            $name = $role instanceof Role ? $role->value : $role;
            $this->staff($role);
            foreach ($paths as $path => $status) {
                $this->get('/admin/'.$path)->assertStatus($status, "{$name} on /admin/{$path}");
            }
        }
    }

    public function test_dashboard_renders_for_every_staff_role(): void
    {
        foreach ([Role::SuperAdmin, Role::Administrator, 'editor', 'reviewer', Role::Advisor] as $role) {
            $this->staff($role);
            $this->get('/admin')->assertOk();
        }
    }

    public function test_editor_creates_a_draft_and_cannot_set_state_or_placement(): void
    {
        $editor = $this->staff('editor');
        $programme = ScholarshipProgramme::factory()->create();

        Livewire::test(CreateScholarshipCycle::class)->fillForm([
            'programme_id' => $programme->id, 'cycle_key' => '2029', 'slug' => '2029', 'intake_year' => 2029, 'funding_type' => 'unknown',
            'featured' => true, 'sponsored' => true, 'fully_funded_verified' => true,
        ])->call('create')->assertHasNoFormErrors();

        $cycle = ScholarshipCycle::firstOrFail();
        $this->assertSame('draft', $cycle->publication_state->value);
        $this->assertSame($editor->id, $cycle->created_by);
        $this->assertFalse($cycle->featured);
        $this->assertFalse($cycle->sponsored);
        $this->assertFalse($cycle->fully_funded_verified);
    }

    public function test_editor_submits_reviewer_publishes_through_the_ui_and_editor_cannot_publish(): void
    {
        $editor = $this->staff('editor');
        $cycle = $this->draftWithSource($editor);

        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->assertActionVisible('submit')->assertActionHidden('approve')->assertActionHidden('publish')
            ->callAction('submit');
        $this->assertSame('in_review', $cycle->fresh()->publication_state->value);

        // Once submitted the editor can no longer open it for editing, so cannot approve or publish it.
        $this->get('/admin/scholarship-cycles/'.$cycle->id.'/edit')->assertForbidden();

        $reviewer = $this->staff('reviewer');
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->assertActionVisible('approve')->callAction('approve', ['notes' => 'Checked against the source']);
        $this->assertSame('approved', $cycle->fresh()->publication_state->value);
        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])
            ->assertActionVisible('publish')->callAction('publish');

        $fresh = $cycle->fresh();
        $this->assertSame('published', $fresh->publication_state->value);
        $this->assertSame($reviewer->id, $fresh->verified_by);
    }

    public function test_a_reviewer_cannot_approve_their_own_cycle_through_the_ui(): void
    {
        $reviewer = $this->staff('reviewer');
        $cycle = $this->draftWithSource($reviewer);
        app(CycleWorkflow::class)->submitForReview($cycle, $reviewer);

        Livewire::test(EditScholarshipCycle::class, ['record' => $cycle->getKey()])->callAction('approve');
        $this->assertSame('in_review', $cycle->fresh()->publication_state->value);
    }

    public function test_editors_may_open_a_published_cycle_to_propose_changes_but_cannot_change_its_child_records(): void
    {
        $cycle = ScholarshipCycle::factory()->published()->create();
        $this->staff('editor');
        $this->get('/admin/scholarship-cycles/'.$cycle->id.'/edit')->assertOk();
        $this->assertFalse(auth()->user()->can('editDirect', $cycle));
        $this->staff('reviewer');
        $this->get('/admin/scholarship-cycles/'.$cycle->id.'/edit')->assertOk();
        $this->assertTrue(auth()->user()->can('editDirect', $cycle));
    }

    public function test_deadline_relation_manager_records_a_new_version_and_refuses_bad_input(): void
    {
        $this->staff('reviewer');
        $cycle = ScholarshipCycle::factory()->published()->create();

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $cycle, 'pageClass' => EditScholarshipCycle::class])
            ->callTableAction('change', data: ['route_id' => '', 'date_type' => 'exact', 'local_closes_at' => '2030-03-31T17:00', 'source_timezone' => 'Asia/Shanghai', 'reason' => 'Initial'])
            ->assertHasNoTableActionErrors();
        $d = $cycle->currentDeadlines()->first();
        $this->assertSame('2030-03-31 09:00:00', $d->closes_at_utc->utc()->format('Y-m-d H:i:s'));

        Livewire::test(DeadlinesRelationManager::class, ['ownerRecord' => $cycle, 'pageClass' => EditScholarshipCycle::class])
            ->callTableAction('change', data: ['route_id' => '', 'date_type' => 'date_only', 'closes_on' => '2030-04-30', 'reason' => 'Funder changed to date only']);
        $this->assertSame(2, $cycle->deadlines()->count());
        $this->assertNull($cycle->currentDeadlines()->first()->closes_at_utc); // date-only never carries a closing instant
    }

    public function test_advisors_only_see_their_assigned_enquiries(): void
    {
        $advisor = $this->staff(Role::Advisor);
        $mine = $this->enquiry(['owner_id' => $advisor->id, 'name' => 'Mine']);
        $theirs = $this->enquiry(['owner_id' => User::factory()->role(Role::Advisor)->create()->id, 'name' => 'Theirs']);
        $unassigned = $this->enquiry(['name' => 'Unassigned']);

        Livewire::test(ListEnquiries::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs, $unassigned]);
        $this->get('/admin/enquiries/'.$theirs->getRouteKey().'/edit')->assertNotFound();
    }

    public function test_service_manager_assigns_and_the_status_changes_with_an_audit_entry(): void
    {
        $manager = $this->staff(Role::Administrator);
        $advisor = User::factory()->role(Role::Advisor)->create();
        $enquiry = $this->enquiry(['name' => 'Student']);

        Livewire::test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])
            ->fillForm(['status' => 'new', 'owner_id' => $advisor->id])->call('save')->assertHasNoFormErrors();
        $enquiry->refresh();
        $this->assertSame($advisor->id, $enquiry->owner_id);
        $this->assertSame('assigned', $enquiry->status->value);
        $this->assertDatabaseHas('audits', ['action' => 'enquiry.updated', 'actor_id' => $manager->id]);
    }

    public function test_an_advisor_cannot_reassign_an_enquiry(): void
    {
        $advisor = $this->staff(Role::Advisor);
        $other = User::factory()->role(Role::Advisor)->create();
        $enquiry = $this->enquiry(['owner_id' => $advisor->id]);

        try {
            Livewire::test(EditEnquiry::class, ['record' => $enquiry->getRouteKey()])->fillForm(['owner_id' => $other->id])->call('save');
        } catch (\Throwable) {
        }
        $this->assertSame($advisor->id, $enquiry->fresh()->owner_id);
    }

    public function test_internal_notes_stay_internal_and_a_public_reply_records_first_response(): void
    {
        $advisor = $this->staff(Role::Advisor);
        $enquiry = $this->enquiry(['owner_id' => $advisor->id]);
        $rm = MessagesRelationManager::class;

        Livewire::test($rm, ['ownerRecord' => $enquiry, 'pageClass' => EditEnquiry::class])
            ->callTableAction('create', data: ['visibility' => 'internal', 'body' => 'Looks like a duplicate'])
            ->callTableAction('create', data: ['visibility' => 'public', 'body' => 'Hello, we can help.']);

        $this->assertSame(1, $enquiry->publicMessages()->count());
        $this->assertNotNull($enquiry->fresh()->first_response_at);
    }

    public function test_confirming_a_consultation_stores_the_time_in_utc_and_marks_it_confirmed(): void
    {
        $manager = $this->staff(Role::Administrator);
        $c = new Consultation(['name' => 'S', 'email' => 's@example.test', 'preferred_from' => now()->addDay(), 'student_timezone' => 'Asia/Karachi', 'contact_method' => 'email']);
        $c->save();

        Livewire::test(ManageConsultations::class)->callTableAction('confirm', $c, data: ['start' => '2030-05-01T15:00', 'timezone' => 'Asia/Karachi', 'duration' => 45, 'advisor_id' => $manager->id]);
        $c->refresh();
        $this->assertSame('confirmed', $c->status);
        $this->assertSame('2030-05-01 10:00:00', $c->confirmed_start_utc->utc()->format('Y-m-d H:i:s')); // PKT = UTC+5
        $this->assertSame(45, $c->duration_minutes);
    }

    public function test_editors_cannot_publish_articles_even_by_choosing_the_status(): void
    {
        $editor = $this->staff('editor');
        // "Published" is not even offered to editors, and a crafted request is rejected by validation.
        Livewire::test(ManageArticles::class)->callAction('create', data: ['title' => 'Guide', 'slug' => 'guide', 'category' => 'guide', 'status' => 'published', 'body' => '<p>x</p>'])
            ->assertHasActionErrors(['status']);
        $this->assertSame(0, Article::count());

        Livewire::test(ManageArticles::class)->callAction('create', data: ['title' => 'Guide', 'slug' => 'guide', 'category' => 'guide', 'status' => 'in_review', 'body' => '<p>x</p>']);
        $article = Article::firstOrFail();
        $this->assertSame('in_review', $article->status);
        $this->assertSame($editor->id, $article->author_id);
        $this->assertSame(1, $article->revisions()->count());
    }

    public function test_the_article_status_guard_downgrades_publish_attempts_by_non_publishers(): void
    {
        $this->staff('editor');
        $this->assertSame('in_review', ManageArticles::guardStatus(['status' => 'published'])['status']);
        $this->staff('reviewer');
        $out = ManageArticles::guardStatus(['status' => 'published']);
        $this->assertSame('published', $out['status']);
        $this->assertNotNull($out['published_at']);
    }

    public function test_only_super_admins_can_grant_roles_and_nobody_can_suspend_themselves(): void
    {
        $admin = $this->staff(Role::Administrator);
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->getKey()])->fillForm(['name' => $target->name, 'status' => 'active', 'roles' => ['super_admin']])->call('save');
        $this->assertSame([], $target->fresh()->roleKeys()); // administrators cannot grant roles

        $super = $this->staff(Role::SuperAdmin);
        Livewire::test(EditUser::class, ['record' => $target->getKey()])->fillForm(['name' => $target->name, 'status' => 'active', 'roles' => ['advisor']])->call('save');
        $this->assertSame(['advisor'], $target->fresh()->roleKeys());
        $this->assertDatabaseHas('audits', ['action' => 'user.roles_changed', 'actor_id' => $super->id]);

        try {
            Livewire::test(EditUser::class, ['record' => $super->getKey()])->fillForm(['name' => $super->name, 'status' => 'suspended', 'roles' => ['super_admin']])->call('save');
        } catch (\Throwable) {
        }
        $this->assertSame('active', $super->fresh()->status);
    }

    public function test_administrators_cannot_edit_super_administrators(): void
    {
        $super = User::factory()->role(Role::SuperAdmin)->create();
        $this->staff(Role::Administrator);
        $this->get('/admin/users/'.$super->id.'/edit')->assertForbidden();
    }

    public function test_suspending_a_user_from_the_admin_ends_their_sessions(): void
    {
        $this->staff(Role::SuperAdmin);
        $victim = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $victim->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::test(EditUser::class, ['record' => $victim->getKey()])->fillForm(['name' => $victim->name, 'status' => 'suspended', 'roles' => []])->call('save');
        $this->assertSame('suspended', $victim->fresh()->status);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $victim->id)->count());
    }

    public function test_cycle_list_filters_and_renders(): void
    {
        $this->staff('reviewer');
        $stale = ScholarshipCycle::factory()->published()->create(['review_due_at' => now()->subDay()]);
        $fresh = ScholarshipCycle::factory()->published()->create();
        $this->assertTrue($stale->fresh()->needsRecheck());
        $this->assertFalse($fresh->fresh()->needsRecheck());
        $list = Livewire::test(ListScholarshipCycles::class)->assertCanSeeTableRecords([$stale, $fresh]);
        $list->set('tableFilters.needs_recheck.isActive', true)->assertCanSeeTableRecords([$stale])->assertCanNotSeeTableRecords([$fresh]);
    }

    private function enquiry(array $attrs = []): Enquiry
    {
        $e = new Enquiry(['category' => 'advisory', 'name' => $attrs['name'] ?? 'Student', 'email' => 's@example.test', 'message' => 'A long enough message']);
        $e->owner_id = $attrs['owner_id'] ?? null;
        $e->save();

        return $e;
    }
}
