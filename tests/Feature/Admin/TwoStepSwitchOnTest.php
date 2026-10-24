<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The staff panel's authenticator requirement is decided when the app boots, so these tests switch SAS_TWO_STEP on before it does. */
class TwoStepSwitchOnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('SAS_TWO_STEP=true');
        $_ENV['SAS_TWO_STEP'] = $_SERVER['SAS_TWO_STEP'] = 'true';
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('SAS_TWO_STEP');
        unset($_ENV['SAS_TWO_STEP'], $_SERVER['SAS_TWO_STEP']);
    }

    public function test_staff_without_mfa_are_forced_to_set_it_up_first_when_two_step_is_on(): void
    {
        $editor = User::factory()->role(Role::Administrator)->create();
        $this->actingAs($editor)->get('/admin')->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
        $this->actingAs($editor)->get('/admin/scholarship-cycles')->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }
}
