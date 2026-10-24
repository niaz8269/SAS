<?php

namespace Database\Seeders;

use App\Domain\Counselling\Counselling;
use App\Enums\Role;
use App\Models\PartnerOrganisation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test LOGINS ONLY, one per role, so every portal can be tried. It creates no scholarships, universities, articles or any other
 * public content (that must always be real). Refuses to run in production: `php artisan db:seed --class=DemoAccountsSeeder`.
 *
 * Staff must use an authenticator app, so every demo staff account is given the same well-known demo secret and recovery codes
 * (local only): add the secret JBSWY3DPEHPK3PXP to any authenticator app, or sign in with a recovery code, for example
 * demo-code-0001 (each code works once; run the seeder again to restore them).
 */
class DemoAccountsSeeder extends Seeder
{
    public const MFA_SECRET = 'JBSWY3DPEHPK3PXP';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoAccountsSeeder refuses to run in production.');

            return;
        }

        $password = env('SAS_DEMO_PASSWORD', 'demo-password-123');
        $codes = collect(range(1, 8))->map(fn ($i) => sprintf('demo-code-%04d', $i))->all();

        foreach ([Role::SuperAdmin, Role::Administrator, Role::Editor, Role::Reviewer, Role::ServiceManager, Role::Advisor, Role::Specialist, Role::Finance] as $role) {
            $user = User::firstOrCreate(['email' => 'demo-'.$role->value.'@example.test'], ['name' => 'Demo '.$role->label(), 'password' => $password]);
            $user->forceFill(['email_verified_at' => now(), 'app_authentication_secret' => self::MFA_SECRET, 'app_authentication_recovery_codes' => $codes])->save();
            $user->userRoles()->firstOrCreate(['role' => $role->value]);
        }

        // A recruiting agent: manages students and their applications (sign in as demo-agent@example.test).
        $agentOrg = PartnerOrganisation::firstOrCreate(['name' => 'Demo Recruiting Agency (test account)'], ['kind' => 'agent', 'contact_email' => 'demo-agent@example.test']);
        $agent = User::firstOrCreate(['email' => 'demo-agent@example.test'], ['name' => 'Demo Agent', 'password' => $password]);
        $agent->forceFill(['email_verified_at' => now(), 'partner_organisation_id' => $agentOrg->id])->save();
        $agent->userRoles()->firstOrCreate(['role' => Role::PartnerManager->value]);

        $student = User::firstOrCreate(['email' => 'demo-student@example.test'], ['name' => 'Demo Student', 'password' => $password]);
        $student->forceFill(['email_verified_at' => now()])->save();

        // The demo advisor is the demo student's counsellor, with a first note and message, so the Students screen is not empty.
        $advisor = User::where('email', 'demo-advisor@example.test')->first();
        if ($advisor && ! $student->counsellor_id) {
            $counselling = app(Counselling::class);
            $counselling->assign($student, $advisor, $advisor);
            $counselling->followUp($student, $advisor, 'contacted', 'A master in computer science in China, ideally with a scholarship.', 'First call done. Waiting for the passport and degree certificate.');
            $counselling->message($student, $advisor, 'Welcome! Please upload your passport and degree certificate, then tell me which universities you like.');
        }
    }
}
