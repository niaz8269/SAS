<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** When the mail service refuses us, sign-up and password reset must not crash with a 500. */
class MailFailureTest extends TestCase
{
    use RefreshDatabase;

    private function brokenMail(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.scheme' => null]);
    }

    public function test_registering_still_works_and_tells_the_person_when_the_email_cannot_be_sent(): void
    {
        $this->brokenMail();

        $response = $this->post('/register', ['name' => 'Amna Khan', 'email' => 'amna@example.org', 'password' => 'abcdef12', 'password_confirmation' => 'abcdef12', 'accept_privacy' => '1']);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'amna@example.org']);
        $this->assertDatabaseHas('email_verification_codes', ['email' => 'amna@example.org']);
        $this->followRedirects($response)->assertOk()->assertSee('We could not send the email just now');
    }

    public function test_asking_for_a_new_code_reports_the_failure_instead_of_a_500(): void
    {
        $this->brokenMail();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect()->assertSessionHas('mail_error');
    }

    public function test_the_password_reset_form_gives_its_usual_answer_when_mail_is_down(): void
    {
        $this->brokenMail();
        User::factory()->create(['email' => 'real@example.org']);

        $this->post('/forgot-password', ['email' => 'real@example.org'])->assertRedirect()->assertSessionHas('status');
    }
}
