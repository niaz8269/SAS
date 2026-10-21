<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class MakeStaff extends Command
{
    protected $signature = 'sas:make-staff {email} {name} {role : one of the staff roles} {--no-mail : do not send the password reset link}';

    protected $description = 'Create (or promote) a named staff account. A random password is set and a reset link is emailed; MFA is enforced at first login.';

    public function handle(): int
    {
        $role = Role::tryFrom($this->argument('role'));
        if (! $role || ! in_array($role->value, config('sas.staff_roles'), true)) {
            $this->error('Role must be one of: '.implode(', ', config('sas.staff_roles')));

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        $created = false;
        if (! $user) {
            $user = User::create(['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => Str::random(40)]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $created = true;
        }
        $user->userRoles()->firstOrCreate(['role' => $role->value]);
        \App\Domain\Editorial\AuditLog::record('user.role_granted_cli', $user, null, ['role' => $role->value]);

        $this->info(($created ? 'Created' : 'Updated').' '.$user->email.' as '.$role->label().'.');
        if ($created && ! $this->option('no-mail')) {
            Password::sendResetLink(['email' => $user->email]);
            $this->line('A password reset link was sent. The user must set up an authenticator app at first sign-in.');
        }

        return self::SUCCESS;
    }
}
