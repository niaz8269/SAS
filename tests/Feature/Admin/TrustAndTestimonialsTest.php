<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Filament\Resources\TrustItems\Pages\ManageTrustItems;
use App\Models\Testimonial;
use App\Models\TrustItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrustAndTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role = Role::SuperAdmin): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create();
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_ships_with_no_testimonials_and_no_testimonial_section_on_the_homepage(): void
    {
        $this->assertSame(0, Testimonial::count());
        $this->get('/')->assertOk()->assertDontSee('What students say');
    }

    public function test_default_trust_points_are_factual_and_editable(): void
    {
        $this->assertSame(3, TrustItem::count());
        $this->get('/')->assertSee('Why you can rely on the listings')->assertSee('Second-person review');
    }

    public function test_a_quote_without_recorded_consent_can_never_be_published(): void
    {
        $t = Testimonial::create(['name' => 'A. Student', 'quote' => 'Helpful.', 'published' => true]);
        $this->assertFalse($t->fresh()->published);
        $t2 = Testimonial::create(['name' => 'B. Student', 'quote' => 'Clear.', 'published' => true, 'consent_given' => true]); // consent but no date
        $this->assertFalse($t2->fresh()->published);
        $this->get('/')->assertDontSee('Helpful.')->assertDontSee('Clear.');
    }

    public function test_consented_published_quote_shows_and_withdrawing_consent_hides_it(): void
    {
        $t = Testimonial::create(['name' => 'Amina K.', 'context' => "Master's student, 2026", 'quote' => 'The official links saved me hours.', 'consent_given' => true, 'consent_date' => '2026-10-01', 'published' => true]);
        $this->get('/')->assertSee('What students say')->assertSee('The official links saved me hours.')->assertSee('Amina K.')->assertSee("Master's student, 2026");

        $t->update(['consent_given' => false]);
        $this->assertFalse($t->fresh()->published);
        $this->get('/')->assertDontSee('The official links saved me hours.')->assertDontSee('What students say');
    }

    public function test_admin_form_requires_a_consent_date_when_consent_is_ticked(): void
    {
        $this->staff();
        Livewire::test(ManageTestimonials::class)->callAction('create', ['name' => 'Li W.', 'quote' => 'Great.', 'consent_given' => true])->assertHasFormErrors(['consent_date']);
        Livewire::test(ManageTestimonials::class)
            ->callAction('create', ['name' => 'Li W.', 'quote' => 'Great.', 'consent_given' => true, 'consent_date' => '2026-10-02', 'consent_note' => 'Email 2 Oct', 'published' => true])->assertHasNoFormErrors();
        $this->assertTrue(Testimonial::first()->published);
        $this->assertDatabaseHas('audits', ['action' => 'site.Testimonial_created']);
    }

    public function test_trust_items_can_be_added_as_partners_and_deleted(): void
    {
        $this->staff();
        Livewire::test(ManageTrustItems::class)->callAction('create', ['kind' => 'partner', 'title' => 'Example University', 'url' => 'https://example.edu', 'enabled' => true])->assertHasNoFormErrors();
        Livewire::test(ManageTrustItems::class)
            ->callAction('create', ['kind' => 'point', 'title' => 'Bad', 'url' => 'javascript:alert(1)', 'enabled' => true])->assertHasFormErrors(['url']);
        $this->get('/')->assertSee('Example University');

        $item = TrustItem::where('title', 'Example University')->first();
        Livewire::test(ManageTrustItems::class)->callAction(TestAction::make('delete')->table($item));
        $this->get('/')->assertDontSee('Example University');
    }

    public function test_only_site_managers_can_open_the_screens(): void
    {
        $this->staff(Role::Advisor);
        $this->get('/admin/testimonials')->assertForbidden();
        $this->get('/admin/trust-items')->assertForbidden();
    }
}
