<?php

namespace App\Domain\Cases;

use App\Domain\Communication\Preferences;
use App\Models\ServiceCase;
use App\Notifications\CaseUpdateNotification;

/** Tells a student about a change to their case or fees, through the channels they allowed for account messages. */
class CaseNotifier
{
    public function tell(ServiceCase $case, string $message): void
    {
        $student = $case->student;
        if (! $student || ! $student->isActive()) {
            return;
        }
        Preferences::ensureDefaults($student);
        $channels = [];
        if (Preferences::enabled($student, 'transactional', 'in_app')) {
            $channels[] = 'database';
        }
        if (Preferences::enabled($student, 'transactional', 'mail') && $student->hasVerifiedEmail()) {
            $channels[] = 'mail';
        }
        if ($channels !== []) {
            $student->notify(new CaseUpdateNotification($message, route('student.cases.show', $case), $channels));
        }
    }
}
