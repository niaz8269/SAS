<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The staff home page: a greeting by first name, key figures only for the screens a person may open, and the funnel and fees cards. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(Role $role, string $name = 'Niaz Khan'): User
    {
        Filament::setCurrentPanel('admin');
        $user = User::factory()->role($role)->create(['name' => $name]);
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'app_authentication_recovery_codes' => ['aaaa-bbbb']])->save();
        $this->actingAs($user);

        return $user;
    }

    public function test_the_super_admin_sees_a_greeting_the_key_figures_and_the_funnel_and_fees_cards(): void
    {
        $this->actAs(Role::SuperAdmin);

        $this->get('/admin')->assertOk()
            ->assertSee('Welcome back, Niaz')->assertDontSee('Welcome back, Niaz Khan')
            ->assertSee('Open enquiries')->assertSee('Conversion')->assertSee('Awaiting payment')
            ->assertSee('Enquiry funnel')->assertSee('Fees and payments');
    }

    public function test_a_counsellor_sees_no_money_or_enquiry_figures(): void
    {
        $this->actAs(Role::Advisor);

        $this->get('/admin')->assertOk()->assertSee('Welcome back')
            ->assertDontSee('Awaiting payment')->assertDontSee('Fees and payments')->assertDontSee('Enquiry funnel');
    }
}
