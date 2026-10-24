<?php

namespace App\Domain\Counselling;

use App\Domain\Communication\Preferences;
use App\Domain\Editorial\AuditLog;
use App\Models\CounsellorMessage;
use App\Models\StudentFollowUp;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use InvalidArgumentException;

/** What a counsellor does for a student: take them on, record where they stand, and talk to them. */
class Counselling
{
    /** Assigns (or, with null, removes) the student's counsellor. A counsellor-to-be must be active staff who may look after students. */
    public function assign(User $student, ?User $counsellor, ?User $actor = null): void
    {
        if ($counsellor && ! ($counsellor->isStaff() && $counsellor->hasPermission('students.assigned'))) {
            throw new InvalidArgumentException('That person cannot look after students.');
        }
        $before = $student->counsellor_id;
        $student->forceFill(['counsellor_id' => $counsellor?->id])->save();
        AuditLog::record('student.counsellor_assigned', $student, ['counsellor' => $before], ['counsellor' => $counsellor?->id], null, $actor);
        if ($counsellor && $counsellor->id !== $before) {
            $this->tell($student, $counsellor->name.' is now your SAS counsellor. You can message them from your workspace.');
        }
    }

    /** Records a status, what the student wants and the counsellor's comment. Staff only; the student never sees it. */
    public function followUp(User $student, User $author, string $status, ?string $wants, ?string $comment): StudentFollowUp
    {
        if (! array_key_exists($status, StudentFollowUp::STATUSES)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        $entry = new StudentFollowUp(['status' => $status, 'wants' => filled($wants) ? trim($wants) : null, 'comment' => filled($comment) ? trim($comment) : null]);
        $entry->forceFill(['student_id' => $student->id, 'author_id' => $author->id])->save();
        AuditLog::record('student.follow_up', $student, null, ['status' => $status], null, $author); // the text itself is not logged

        return $entry;
    }

    /** A message in the student's conversation with their counsellor. A reply from staff notifies the student. */
    public function message(User $student, User $author, string $body): CounsellorMessage
    {
        $message = new CounsellorMessage(['body' => trim($body)]);
        $message->forceFill(['student_id' => $student->id, 'author_id' => $author->id])->save();
        if ($author->id !== $student->id) {
            $this->tell($student, ($author->name ?: 'Your counsellor').' sent you a message.');
        }

        return $message;
    }

    /** Whether the last message in the conversation is the student's, so a counsellor still owes a reply. */
    public static function awaitingReply(User $student): bool
    {
        $last = CounsellorMessage::where('student_id', $student->id)->latest('id')->first();

        return $last !== null && $last->author_id === $student->id;
    }

    private function tell(User $student, string $message): void
    {
        if (! $student->isActive()) {
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
            $student->notify(new CaseUpdateNotification($message, route('student.counsellor'), $channels, 'Open your messages'));
        }
    }
}
