<?php

namespace Tests\Feature\Partners;

use App\Enums\Role;
use App\Filament\Resources\PartnerOrganisations\Pages\CreatePartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\Pages\EditPartnerOrganisation;
use App\Filament\Resources\PartnerOrganisations\RelationManagers\UsersRelationManager;
use App\Models\PartnerOrganisation;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The partner area is the recruiting-agent portal (see AgentPortalTest for what agents do). */
class PartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
    }

    private function agent(): array
    {
        $org = PartnerOrganisation::create(['name' => 'Example Agency']);
        $user = User::factory()->role(Role::PartnerManager)->create();
        $user->forceFill(['partner_organisation_id' => $org->id])->save();

        return [$user->refresh(), $org];
    }

    private function staff(Role $role): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_every_partner_organisation_is_a_recruiting_agent(): void
    {
        $this->assertSame(['agent' => 'Recruiting agent'], PartnerOrganisation::KINDS);
        [$user, $org] = $this->agent();
        $this->assertSame('agent', $org->refresh()->kind);
        $this->assertTrue($user->isAgent());
    }

    public function test_the_old_scholarship_submission_area_is_gone(): void
    {
        [$user] = $this->agent();
        foreach (['/partner/submissions', '/partner/submissions/new'] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }
        $this->get(route('partner.home'))->assertOk()->assertSee('Backend home')->assertDontSee('Submit a scholarship');
    }

    public function test_students_and_suspended_agents_cannot_use_the_partner_area(): void
    {
        $this->actingAs(User::factory()->create())->get(route('partner.home'))->assertForbidden();
        [$user, $org] = $this->agent();
        $this->actingAs($user)->get(route('partner.home'))->assertOk();
        $org->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($user->fresh())->get(route('partner.home'))->assertForbidden();
    }

    public function test_after_sign_in_each_kind_of_user_lands_in_their_own_area(): void
    {
        [$partner] = $this->agent();
        $this->assertSame(route('partner.home'), $partner->homeUrl());
        $this->assertSame('/admin', User::factory()->role(Role::Advisor)->create()->homeUrl());
        $this->assertSame(route('student.dashboard'), User::factory()->create()->homeUrl());
        $partner->forceFill(['email_verified_at' => now(), 'password' => 'a-long-test-password'])->save();
        $this->post('/login', ['email' => $partner->email, 'password' => 'a-long-test-password'])->assertRedirect(route('partner.home'));
    }

    public function test_the_admin_screens_create_agencies_and_their_people(): void
    {
        $this->staff(Role::Administrator);
        Livewire::test(CreatePartnerOrganisation::class)->fillForm(['name' => 'New Agency', 'status' => 'active'])->call('create')->assertHasNoFormErrors();
        $org = PartnerOrganisation::firstWhere('name', 'New Agency');
        $this->assertNotNull($org);
        $this->assertSame('agent', $org->kind);
        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $org, 'pageClass' => EditPartnerOrganisation::class])
            ->callAction(TestAction::make('create')->table(), ['name' => 'Pat Agent', 'email' => 'pat@agency.test', 'password' => 'a-very-long-password']);
        $person = User::firstWhere('email', 'pat@agency.test');
        $this->assertNotNull($person);
        $this->assertSame($org->id, $person->partner_organisation_id);
        $this->assertTrue($person->hasRole(Role::PartnerManager));
        $this->assertTrue($person->isAgent());

        auth()->logout();
        $this->staff(Role::Advisor);
        $this->assertFalse(auth()->user()->can('viewAny', PartnerOrganisation::class));
    }

    public function test_the_public_page_for_agents_explains_how_to_get_an_account(): void
    {
        $this->get(route('partners'))->assertOk()->assertSee('For recruiting agents')->assertSee('Agent accounts are created by our team')->assertDontSee('list a scholarship');
    }
}
