<?php

namespace App\Console\Commands;

use App\Domain\Admin\Desk;
use App\Models\User;
use App\Notifications\AdminAlertDigest;
use Illuminate\Console\Command;

class SendAdminAlerts extends Command
{
    protected $signature = 'sas:admin-alerts';

    protected $description = 'Email the admins what is late: slow enquiries, students waiting for a reply, reviews and fixes that waited too long, unpaid agency charges. Nothing late means no email.';

    public function handle(Desk $desk): int
    {
        $groups = $desk->groups();
        $lateReviews = $desk->lateReviews();
        if ($desk->lateCount($groups) === 0) {
            $this->info('Nothing is late.');

            return self::SUCCESS;
        }
        $admins = User::whereHas('userRoles', fn ($q) => $q->whereIn('role', ['administrator', 'super_admin']))->where('status', 'active')->whereNotNull('email_verified_at')->get()
            ->filter(fn (User $u) => $u->hasPermission('cases.manage'));
        $admins->each(fn (User $u) => $u->notify(new AdminAlertDigest($groups, $lateReviews)));
        $this->info('Sent to '.$admins->count().' '.str('admin')->plural($admins->count()).'.');

        return self::SUCCESS;
    }
}
