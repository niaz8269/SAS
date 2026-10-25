<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every password box has a show/hide eye; boxes for a NEW password also have the weak / medium / strong bar. */
class PasswordInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_has_the_eye_but_no_strength_bar(): void
    {
        $this->get('/login')->assertOk()->assertSee('Show password', false)->assertSee(':type="show ? \'text\' : \'password\'"', false)->assertDontSee('Password strength:', false);
    }

    public function test_register_has_the_eye_on_both_boxes_and_the_bar_on_the_new_password_only(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'aria-label="Show password"'));
        $this->assertSame(1, substr_count($html, 'Password strength:'));
        $this->assertStringContainsString('At least 8 characters, with letters and numbers.', $html);
        $this->assertStringContainsString('aria-describedby="pw-hint"', $html);
    }

    public function test_the_reset_page_has_the_bar_on_the_new_password(): void
    {
        $this->get('/reset-password/sometoken?email=a@b.test')->assertOk()->assertSee('Password strength:', false)->assertSee('Show password', false);
    }

    public function test_the_agent_password_page_has_the_bar_only_for_the_new_password(): void
    {
        $agent = User::factory()->role(Role::PartnerManager)->create();
        $html = $this->actingAs($agent)->get('/partner/password')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'aria-label="Show password"'));
        $this->assertSame(1, substr_count($html, 'Password strength:'));
    }

    public function test_no_page_still_uses_a_bare_password_input(): void
    {
        foreach (glob(resource_path('views/{auth,student,partner/agent}/*.blade.php'), GLOB_BRACE) as $file) {
            $this->assertStringNotContainsString('type="password"', (string) file_get_contents($file), basename($file).' should use <x-password-input>');
        }
    }
}
