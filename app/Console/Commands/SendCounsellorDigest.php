<?php

namespace App\Console\Commands;

use App\Domain\Counselling\Workload;
use App\Models\User;
use App\Notifications\CounsellorDigest;
use Illuminate\Console\Command;

class SendCounsellorDigest extends Command
{
    protected $signature = 'sas:counsellor-digest';

    protected $description = 'Email each counsellor their list for today (replies owed, follow-ups due, quiet students, deadlines). Counsellors with nothing to do get no email.';

    public function handle(Workload $workload): int
    {
        $sent = 0;
        User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['advisor', 'specialist', 'service_manager', 'administrator']))->where('status', 'active')->whereNotNull('email_verified_at')->get()
            ->filter(fn (User $u) => $u->hasPermission('students.assigned'))
            ->each(function (User $user) use ($workload, &$sent) {
                $work = $workload->for($user);
                if ($work['total'] > 0) {
                    $user->notify(new CounsellorDigest($work));
                    $sent++;
                }
            });
        $this->info("Sent $sent digest".($sent === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
