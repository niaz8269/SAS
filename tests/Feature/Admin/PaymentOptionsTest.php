<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\PaymentOptions\Pages\ManagePaymentOptions;
use App\Models\PaymentOption;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentOptionsTest extends TestCase
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

    public function test_invoice_by_email_is_the_default_and_enabled_options_are_listed_on_the_services_and_refund_pages(): void
    {
        $this->assertSame(['Invoice by email'], PaymentOption::available()->pluck('name')->all());
        PaymentOption::create(['name' => 'Pay online', 'kind' => 'online', 'link_url' => 'https://pay.example.org/sas', 'instructions' => 'Pay by card or Alipay.', 'enabled' => true, 'sort' => 20]);
        PaymentOption::create(['name' => 'Old method', 'kind' => 'bank', 'enabled' => false, 'sort' => 30]);

        foreach (['services.index', 'legal.show'] as $route) {
            $page = $this->get($route === 'legal.show' ? route($route, 'refunds') : route($route))->assertOk();
            $page->assertSee('How to pay')->assertSee('Invoice by email')->assertSee('Pay online')->assertSee('https://pay.example.org/sas', false)->assertDontSee('Old method');
        }
    }

    public function test_staff_with_site_permission_can_add_a_payment_option_and_others_cannot(): void
    {
        $this->staff(Role::SuperAdmin);
        Livewire::test(ManagePaymentOptions::class)->callAction('create', ['name' => 'Bank transfer', 'kind' => 'bank', 'instructions' => 'Details sent with the invoice.', 'enabled' => true])->assertHasNoActionErrors();
        $this->assertDatabaseHas('payment_options', ['name' => 'Bank transfer', 'kind' => 'bank']);

        auth()->logout();
        $this->staff(Role::Advisor);
        $this->get('/admin/payment-options')->assertForbidden();
    }
}
