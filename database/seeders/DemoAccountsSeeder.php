<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test LOGINS ONLY, one per role, so every portal can be tried. It creates no scholarships, universities, articles or any other
 * public content (that must always be real). Refuses to run in production: `php artisan db:seed --class=DemoAccountsSeeder`.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoAccountsSeeder refuses to run in production.');

            return;
        }

        $password = env('SAS_DEMO_PASSWORD', 'demo-password-123');

        foreach ([Role::Administrator, Role::Editor, Role::Reviewer, Role::ServiceManager, Role::Advisor, Role::Specialist, Role::PartnerManager] as $role) {
            $user = User::firstOrCreate(['email' => 'demo-'.$role->value.'@example.test'], ['name' => 'Demo '.$role->label(), 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->userRoles()->firstOrCreate(['role' => $role->value]);
        }

        $student = User::firstOrCreate(['email' => 'demo-student@example.test'], ['name' => 'Demo Student', 'password' => $password]);
        $student->forceFill(['email_verified_at' => now()])->save();
    }
}
