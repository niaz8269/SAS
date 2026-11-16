<?php

namespace App\Console\Commands;

use App\Domain\Editorial\AuditLog;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * One-time reset of the admin side: leaves exactly one staff account, a super admin, and deletes every other staff account
 * (Super admin, Admin and Counsellor). Students, partners (agents) and everything they own are not touched. Leads, applications, reviews, messages
 * and the audit log stay: the columns that name a staff member are set to empty by the database (ON DELETE SET NULL), so only the
 * "assigned to" and "reviewed by" names disappear. A counsellor's own availability slots and reply templates go with the account.
 *
 * Without --yes it only lists what it would do. The new super admin gets a random password and an emailed reset link, so no password
 * is ever typed or printed.
 */
class ResetStaff extends Command
{
    protected $signature = 'sas:reset-staff {email : the one super admin that stays or is created} {name : display name for a new account} {--yes : really do it (without it, only list)} {--no-mail : do not send the reset link}';

    protected $description = 'Delete every staff account except one super admin (created if needed); students, partners and all their data stay.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }
        $staffRoles = config('sas.staff_roles');

        $existing = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($existing && ! $existing->hasRole(...$staffRoles) && ($existing->studentProfile()->exists() || $existing->hasRole(Role::PartnerManager))) {
            $this->error("$email belongs to a student or partner account; use another email for the super admin.");

            return self::FAILURE;
        }

        $others = User::whereHas('userRoles', fn ($q) => $q->whereIn('role', $staffRoles))
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->orderBy('id')->get(['id', 'name', 'email']);

        $this->line(($existing ? 'Keeps and makes the only super admin: ' : 'Creates the only super admin: ').$email);
        $this->line('Staff accounts that will be deleted ('.$others->count().'):');
        foreach ($others as $u) {
            $this->line("  #{$u->id} {$u->name} <{$u->email}>");
        }
        if (! $this->option('yes')) {
            $this->warn('Nothing was changed. Run again with --yes to do it.');

            return self::SUCCESS;
        }

        $created = false;
        DB::transaction(function () use ($email, $existing, $others, $staffRoles, &$created) {
            $user = $existing;
            if (! $user) {
                $user = User::create(['name' => $this->argument('name'), 'email' => $email, 'password' => Str::random(40)]);
                $created = true;
            }
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'status' => 'active', 'suspended_at' => null])->save();
            $user->userRoles()->whereIn('role', $staffRoles)->where('role', '!=', Role::SuperAdmin->value)->delete();
            $user->userRoles()->firstOrCreate(['role' => Role::SuperAdmin->value]);

            $ids = $others->pluck('id')->all();
            if ($ids !== []) {
                DB::table('sessions')->whereIn('user_id', $ids)->delete();
                DB::table('password_reset_tokens')->whereIn('email', $others->pluck('email')->all())->delete();
                User::whereIn('id', $ids)->delete();
            }
            AuditLog::record('user.staff_reset', $user, null, ['super_admin' => $email, 'deleted_accounts' => $others->count(), 'deleted_emails' => $others->pluck('email')->all()]);
        });

        $this->info(($created ? 'Created ' : 'Kept ').$email.' as the only super admin and deleted '.$others->count().' other staff account(s).');
        if (! $this->option('no-mail')) {
            $sent = Password::sendResetLink(['email' => $email]) === Password::RESET_LINK_SENT;
            $this->line($sent ? 'A link to choose the password was emailed to '.$email.'.' : 'The email could not be sent. Use "Forgot password" on the sign-in page once email works.');
        }

        return self::SUCCESS;
    }
}
