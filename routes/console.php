<?php

use Illuminate\Support\Facades\Schedule;

// JOB-02: scheduled work. Locks keep multiple application instances from duplicating it.
Schedule::command('sas:evaluate-deadlines')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('sas:dispatch-reminders')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
Schedule::command('sas:counsellor-digest')->dailyAt('07:30')->onOneServer(); // each counsellor's list for the day
Schedule::command('sas:admin-alerts')->dailyAt('08:00')->onOneServer(); // what is late, for the admins
Schedule::command('sas:heartbeat')->everyMinute(); // lets System health see that the scheduler is alive
Schedule::command('sas:backup')->dailyAt('02:30')->onOneServer(); // database and documents
Schedule::command('sas:reindex-search')->dailyAt('03:30')->onOneServer(); // catches alias, funder and university renames
Schedule::command('sas:retention-cleanup')->daily()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
// Emails and other background jobs: with only the cron line (no always-running worker) the queue is worked here, once a minute, until empty.
if (config('sas.queue_via_cron') && config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --backoff=60 --queue=default,mail,reminders,scanning')->everyMinute()->withoutOverlapping(2);
}
