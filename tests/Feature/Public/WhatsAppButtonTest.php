<?php

namespace Tests\Feature\Public;

use App\Enums\Role;
use App\Models\PartnerOrganisation;
use App\Models\User;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxonomySeeder::class);
        config(['sas.contact_whatsapp' => '+86 176 4041 2827']);
    }

    public function test_the_button_is_on_public_pages_and_the_sign_in_pages(): void
    {
        foreach (['/', '/scholarships', '/contact', '/about', '/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('https://wa.me/8617640412827?text=', false);
        }
    }

    public function test_the_button_is_in_the_student_area(): void
    {
        $student = User::factory()->create();

        foreach (['/student', '/student/applications', '/student/profile'] as $path) {
            $this->actingAs($student)->get($path)->assertOk()->assertSee('https://wa.me/8617640412827', false);
        }
    }

    public function test_the_button_is_in_the_agent_area(): void
    {
        $org = PartnerOrganisation::create(['name' => 'Example Agency']);
        $agent = User::factory()->role(Role::PartnerManager)->create();
        $agent->forceFill(['partner_organisation_id' => $org->id])->save();

        foreach (['/partner', '/partner/students', '/partner/password'] as $path) {
            $this->actingAs($agent->refresh())->get($path)->assertOk()->assertSee('https://wa.me/8617640412827', false);
        }
    }

    public function test_no_button_when_no_whatsapp_number_is_set(): void
    {
        config(['sas.contact_whatsapp' => '']);

        $this->get('/')->assertOk()->assertDontSee('wa.me/', false);
        $this->actingAs(User::factory()->create())->get('/student')->assertOk()->assertDontSee('wa.me/', false);
    }
}
