<?php

use Illuminate\Support\Facades\Schedule;

// JOB-02: scheduled work. Locks keep multiple application instances from duplicating it.
Schedule::command('sas:evaluate-deadlines')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('sas:dispatch-reminders')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
Schedule::command('sas:counsellor-digest')->dailyAt('07:30')->onOneServer(); // each counsellor's list for the day
Schedule::command('sas:reindex-search')->dailyAt('03:30')->onOneServer(); // catches alias, funder and university renames
Schedule::command('sas:retention-cleanup')->daily()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
