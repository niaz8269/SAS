<?php

namespace Tests\Feature\Partners;

use App\Domain\Partners\PartnerSubmissionService;
use App\Enums\PublicationState;
use App\Enums\Role;
use App\Filament\Resources\PartnerOrganisations\Pages\CreatePartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\Pages\EditPartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\RelationManagers\UsersRelationManager;
use App\Models\Country;
use App\Models\PartnerOrganisation;
use App\Models\PartnerSubmission;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
    }

    private function partner(?PartnerOrganisation $org = null): array
    {
        $org ??= PartnerOrganisation::create(['name' => 'Example University', 'kind' => 'university']);
        $user = User::factory()->role(Role::PartnerManager)->create();
        $user->forceFill(['partner_organisation_id' => $org->id])->save();

        return [$user, $org];
    }

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    private function payload(array $over = []): array
    {
        return $over + ['title' => 'Example Excellence Award', 'funder_name' => 'Example University', 'funding_type' => 'full', 'cycle_year' => 2028, 'degree_codes' => ['master'],
            'official_url' => 'https://example.org/award', 'deadline_on' => '2027-12-01', 'summary' => 'A full scholarship.'];
    }

    public function test_a_partner_submits_and_sees_only_their_own_organisation(): void
    {
        [$user, $org] = $this->partner();
        [, $otherOrg] = $this->partner(PartnerOrganisation::create(['name' => 'Rival College', 'kind' => 'university']));
        $rival = new PartnerSubmission(['title' => 'Rival secret scholarship', 'official_url' => 'https://rival.example']);
        $rival->forceFill(['organisation_id' => $otherOrg->id, 'status' => 'submitted'])->save();

        $this->actingAs($user)->get(route('partner.home'))->assertOk()->assertSee('Example University');
        $this->post(route('partner.submissions.store'), $this->payload())->assertRedirect();
        $mine = PartnerSubmission::firstWhere('title', 'Example Excellence Award');
        $this->assertSame($org->id, $mine->organisation_id);
        $this->assertSame('submitted', $mine->status);
        $this->get(route('partner.submissions.index'))->assertOk()->assertSee('Example Excellence Award')->assertDontSee('Rival secret scholarship');
        $this->get(route('partner.submissions.show', $rival))->assertForbidden();
    }

    public function test_a_partner_cannot_send_status_or_organisation_and_must_give_an_official_page(): void
    {
        [$user, $org] = $this->partner();
        [, $other] = $this->partner(PartnerOrganisation::create(['name' => 'Rival College', 'kind' => 'university']));
        $this->actingAs($user)->post(route('partner.submissions.store'), $this->payload(['status' => 'accepted', 'organisation_id' => $other->id, 'editor_note' => 'hacked']))->assertRedirect();
        $s = PartnerSubmission::firstWhere('title', 'Example Excellence Award');
        $this->assertSame('submitted', $s->status);
        $this->assertSame($org->id, $s->organisation_id);
        $this->assertNull($s->editor_note);

        $this->post(route('partner.submissions.store'), $this->payload(['title' => 'No source', 'official_url' => 'javascript:alert(1)']))->assertSessionHasErrors('official_url');
        $this->assertNull(PartnerSubmission::firstWhere('title', 'No source'));
    }

    public function test_students_and_suspended_partners_cannot_use_the_partner_area(): void
    {
        $this->actingAs(User::factory()->create())->get(route('partner.home'))->assertForbidden();
        [$user, $org] = $this->partner();
        $this->actingAs($user)->get(route('partner.home'))->assertOk();
        $org->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($user->fresh())->get(route('partner.home'))->assertForbidden();
    }

    public function test_a_partner_can_edit_only_until_editors_decide_and_sees_the_editor_message(): void
    {
        [$user, $org] = $this->partner();
        $s = new PartnerSubmission($this->payload());
        $s->forceFill(['organisation_id' => $org->id, 'status' => 'needs_changes', 'editor_note' => 'Please add the closing date from the official page.'])->save();
        $this->actingAs($user)->get(route('partner.submissions.show', $s))->assertOk()->assertSee('Please add the closing date')->assertSee('Edit and resend');
        $this->put(route('partner.submissions.update', $s), $this->payload(['title' => 'Corrected title']))->assertRedirect();
        $this->assertSame('submitted', $s->refresh()->status);
        $this->assertSame('Corrected title', $s->title);

        $s->forceFill(['status' => 'accepted'])->save();
        $this->get(route('partner.submissions.edit', $s))->assertForbidden();
    }

    public function test_after_sign_in_each_kind_of_user_lands_in_their_own_area(): void
    {
        [$partner] = $this->partner();
        $this->assertSame(route('partner.home'), $partner->homeUrl());
        $this->assertSame('/admin', User::factory()->role(Role::Advisor)->create()->homeUrl());
        $this->assertSame(route('student.dashboard'), User::factory()->create()->homeUrl());
        $partner->forceFill(['email_verified_at' => now(), 'password' => 'a-long-test-password'])->save();
        $this->post('/login', ['email' => $partner->email, 'password' => 'a-long-test-password'])->assertRedirect(route('partner.home'));
    }

    public function test_editors_review_a_submission_and_turn_it_into_a_draft_listing(): void
    {
        [, $org] = $this->partner();
        $s = new PartnerSubmission($this->payload(['country_id' => Country::where('code', 'DE')->value('id')]));
        $s->forceFill(['organisation_id' => $org->id, 'status' => 'submitted'])->save();
        $manager = $this->staff(Role::ServiceManager);

        $service = app(PartnerSubmissionService::class);
        $service->decide($s, 'needs_changes', 'Add the funding details.', $manager);
        $this->assertSame('needs_changes', $s->refresh()->status);
        $this->assertSame('Add the funding details.', $s->editor_note);

        $cycle = $service->createDraft($s, $manager);
        $this->assertSame(PublicationState::Draft, $cycle->publication_state);
        $this->assertSame('accepted', $s->refresh()->status);
        $this->assertSame($cycle->id, $s->cycle_id);
        $this->assertSame('Example Excellence Award', $cycle->programme->title);
    }

    public function test_the_admin_screens_create_organisations_and_partner_people(): void
    {
        $this->staff(Role::ServiceManager);
        Livewire::test(CreatePartnerOrganisation::class)->fillForm(['name' => 'New Partner', 'kind' => 'funder', 'status' => 'active'])->call('create')->assertHasNoFormErrors();
        $org = PartnerOrganisation::firstWhere('name', 'New Partner');
        $this->assertNotNull($org);
        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $org, 'pageClass' => EditPartnerOrganisation::class])
            ->callAction(TestAction::make('create')->table(), ['name' => 'Pat Partner', 'email' => 'pat@partner.test', 'password' => 'a-very-long-password']);
        $person = User::firstWhere('email', 'pat@partner.test');
        $this->assertNotNull($person);
        $this->assertSame($org->id, $person->partner_organisation_id);
        $this->assertTrue($person->hasRole(Role::PartnerManager));

        auth()->logout();
        $this->staff(Role::Editor);
        $this->assertFalse(auth()->user()->can('viewAny', PartnerOrganisation::class));
    }
}
