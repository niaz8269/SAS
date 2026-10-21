<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderEvent;
use App\Models\NotificationEvent;
use Illuminate\Console\Command;

class DispatchReminders extends Command
{
    protected $signature = 'sas:dispatch-reminders';

    protected $description = 'Queue due deadline reminder events (JOB-02, every 15 minutes).';

    public function handle(): int
    {
        $count = 0;
        NotificationEvent::where('status', 'pending')->where('send_at', '<=', now())->orderBy('id')->chunkById(200, function ($events) use (&$count) {
            foreach ($events as $event) {
                SendReminderEvent::dispatch($event->id);
                $count++;
            }
        });
        $this->info("Dispatched {$count} reminder job(s).");

        return self::SUCCESS;
    }
}
