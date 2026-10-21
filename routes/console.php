<?php

use Illuminate\Support\Facades\Schedule;

// JOB-02: scheduled work. Locks keep multiple application instances from duplicating it.
Schedule::command('sas:evaluate-deadlines')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('sas:dispatch-reminders')->everyFifteenMinutes()->withoutOverlapping(30)->onOneServer();
Schedule::command('sas:retention-cleanup')->daily()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
