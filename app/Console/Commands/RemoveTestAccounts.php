<?php

namespace App\Console\Commands;

use App\Domain\Identity\AccountPrivacy;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes student accounts (people with no staff or agent role) after sign-up tests, the same way "delete my account" does:
 * documents, profile and plans go, the address becomes free to register again. Staff and agent accounts are never touched.
 * It only lists what it would remove until --yes is given.
 */
class RemoveTestAccounts extends Command
{
    protected $signature = 'sas:remove-test-accounts {--email=* : only these addresses} {--all-students : every student account} {--yes : really remove them (otherwise only a list is shown)}';

    protected $description = 'List or remove student accounts made while testing sign-up. Never touches staff or agent accounts.';

    public function handle(AccountPrivacy $privacy): int
    {
        $emails = array_map(fn ($e) => mb_strtolower(trim((string) $e)), (array) $this->option('email'));
        if (! $emails && ! $this->option('all-students')) {
            $this->error('Say which accounts: --email=address (repeat it for several) or --all-students.');

            return self::FAILURE;
        }

        $users = User::query()->orderBy('id')->get()
            ->filter(fn (User $u) => $u->roleKeys() === [])
            ->filter(fn (User $u) => $this->option('all-students') || in_array(mb_strtolower($u->email), $emails, true));

        if ($users->isEmpty()) {
            $this->info('No matching student accounts.');

            return self::SUCCESS;
        }

        foreach ($users as $u) {
            $this->line(sprintf('%s  #%d  %s  (%s)', $this->option('yes') ? 'removing' : 'would remove', $u->id, $u->email, $u->email_verified_at ? 'verified' : 'not verified'));
        }
        if (! $this->option('yes')) {
            $this->warn($users->count().' account(s) listed. Nothing was removed. Run again with --yes to remove them.');

            return self::SUCCESS;
        }

        $files = 0;
        foreach ($users as $u) {
            $files += $privacy->erase($u);
        }
        $this->info('Removed '.$users->count().' account(s) and '.$files.' file(s). Those addresses can register again.');

        return self::SUCCESS;
    }
}
