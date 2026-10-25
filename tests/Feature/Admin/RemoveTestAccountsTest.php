<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveTestAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_until_told_to_remove_and_never_touches_staff_or_agents(): void
    {
        $student = User::factory()->unverified()->create(['email' => 'test1@gmail.com']);
        $other = User::factory()->create(['email' => 'test2@gmail.com']);
        $staff = User::factory()->role(Role::SuperAdmin)->create(['email' => 'owner@sas.test']);
        $agent = User::factory()->role(Role::PartnerManager)->create(['email' => 'agent@agency.test']);

        $this->artisan('sas:remove-test-accounts', ['--all-students' => true])->expectsOutputToContain('would remove')->assertSuccessful();
        $this->assertSame(4, User::count());

        $this->artisan('sas:remove-test-accounts', ['--all-students' => true, '--yes' => true])->expectsOutputToContain('Removed 2 account(s)')->assertSuccessful();
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('users', ['id' => $agent->id]);
    }

    public function test_one_address_can_be_removed_and_then_registered_again(): void
    {
        User::factory()->unverified()->create(['email' => 'again@gmail.com']);
        User::factory()->create(['email' => 'keep@gmail.com']);

        $this->artisan('sas:remove-test-accounts', ['--email' => ['Again@Gmail.com'], '--yes' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'again@gmail.com']);
        $this->assertDatabaseHas('users', ['email' => 'keep@gmail.com']);
        $this->post('/register', ['name' => 'Again', 'email' => 'again@gmail.com', 'password' => 'abcdef12', 'password_confirmation' => 'abcdef12', 'accept_privacy' => '1'])->assertRedirect(route('verification.notice'));
    }

    public function test_it_refuses_to_run_without_saying_which_accounts(): void
    {
        $this->artisan('sas:remove-test-accounts')->assertFailed();
    }
}
