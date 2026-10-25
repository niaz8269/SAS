<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/** One password rule for everyone: at least 8 characters, with letters and numbers. */
class PasswordRuleTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]])->passes();
    }

    public function test_eight_characters_with_letters_and_numbers_are_enough(): void
    {
        $this->assertTrue($this->passes('abcdef12'));
        $this->assertTrue($this->passes('Sunrise2027'));
    }

    public function test_shorter_or_letters_only_or_numbers_only_are_refused(): void
    {
        $this->assertFalse($this->passes('abcde12'));
        $this->assertFalse($this->passes('abcdefgh'));
        $this->assertFalse($this->passes('12345678'));
    }

    public function test_the_pages_that_ask_for_a_password_say_eight(): void
    {
        $this->get('/register')->assertOk()->assertSee('At least 8 characters', false)->assertDontSee('At least 10 characters', false);
    }
}
