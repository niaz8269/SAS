<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates exactly one Super admin, one Admin and one Counsellor, each with a long random password written to a private file (never shown
 * in the terminal, never committed). The super admin then renames the accounts, changes their emails and sets real passwords in the admin
 * (Administration > Users), or each person does it under their own profile.
 */
class CreateStarterStaff extends Command
{
    protected $signature = 'sas:create-starter-staff
        {--super=owner@sas.test : sign-in email of the Super admin}
        {--admin=admin@sas.test : sign-in email of the Admin}
        {--counsellor=counsellor@sas.test : sign-in email of the Counsellor}
        {--reset : also give an account that already exists a new random password}
        {--out= : file for the sign-in details (default storage/app/private/starter-staff.txt)}';

    protected $description = 'Create one Super admin, one Admin and one Counsellor with random passwords saved to a private file.';

    public function handle(): int
    {
        $accounts = [
            [Role::SuperAdmin, 'Super Admin', Str::lower((string) $this->option('super'))],
            [Role::Administrator, 'Admin', Str::lower((string) $this->option('admin'))],
            [Role::Advisor, 'Counsellor', Str::lower((string) $this->option('counsellor'))],
        ];
        if (count(array_unique(array_column($accounts, 2))) !== 3 || collect($accounts)->contains(fn ($a) => ! filter_var($a[2], FILTER_VALIDATE_EMAIL))) {
            $this->error('Give three different, valid email addresses.');

            return self::FAILURE;
        }

        $lines = ['SAS starter staff accounts (created '.now()->toDateTimeString().')', 'Sign in at /admin. Change the names, emails and passwords under Administration > Users, then delete this file.', ''];
        DB::transaction(function () use ($accounts, &$lines) {
            foreach ($accounts as [$role, $name, $email]) {
                $user = User::where('email', $email)->first();
                $password = null;
                if (! $user) {
                    $password = Str::password(20, symbols: false);
                    $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                } elseif ($this->option('reset')) {
                    $password = Str::password(20, symbols: false);
                    $user->forceFill(['password' => $password])->save();
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                $user->userRoles()->firstOrCreate(['role' => $role->value]);
                AuditLog::record('user.starter_staff', $user, null, ['role' => $role->value]);
                $lines[] = sprintf('%-12s %s   %s', $role->label(), $email, $password ?? '(already existed, password unchanged; use --reset for a new one)');
            }
        });

        $out = $this->option('out') ?: storage_path('app/private/starter-staff.txt');
        @mkdir(dirname($out), 0700, true);
        file_put_contents($out, implode(PHP_EOL, $lines).PHP_EOL);
        @chmod($out, 0600);

        $this->info('Created the starter accounts. Sign-in details were saved to: '.$out);

        return self::SUCCESS;
    }
}
