<?php

namespace App\Domain\Counselling;

use App\Domain\Communication\Preferences;
use App\Domain\Documents\DocumentService;
use App\Domain\Editorial\AuditLog;
use App\Models\CounsellorMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\ServiceCase;
use App\Models\StudentFollowUp;
use App\Models\StudentGoal;
use App\Models\User;
use App\Notifications\CaseUpdateNotification;
use App\Notifications\StudentWrote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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
    public function followUp(User $student, User $author, string $status, ?string $wants, ?string $comment, ?string $followUpOn = null, ?string $contactType = null, ?string $nextStep = null): StudentFollowUp
    {
        if (! array_key_exists($status, StudentFollowUp::STATUSES)) {
            throw new InvalidArgumentException('Unknown status.');
        }
        $entry = new StudentFollowUp(['status' => $status, 'wants' => filled($wants) ? trim($wants) : null, 'comment' => filled($comment) ? trim($comment) : null, 'follow_up_on' => filled($followUpOn) ? $followUpOn : null, 'contact_type' => array_key_exists((string) $contactType, StudentFollowUp::CONTACT_TYPES) ? $contactType : null, 'next_step' => filled($nextStep) ? trim($nextStep) : null]);
        $entry->forceFill(['student_id' => $student->id, 'author_id' => $author->id])->save();
        AuditLog::record('student.follow_up', $student, null, ['status' => $status], null, $author); // the text itself is not logged

        return $entry;
    }

    /** A staff comment on one of the student's documents; the student is told and sees it next to the file. */
    public function commentOnDocument(Document $document, User $author, string $body): DocumentComment
    {
        $comment = new DocumentComment(['body' => trim($body)]);
        $comment->forceFill(['document_id' => $document->id, 'author_id' => $author->id])->save();
        AuditLog::record('document.commented', $document, null, null, null, $author);
        $this->tell($document->user, ($author->name ?: 'Your counsellor').' left a comment on your document "'.$document->title.'": '.Str::limit($comment->body, 120));

        return $comment;
    }

    /** Saves what the student is aiming for (blank values are cleared). */
    public function setGoals(User $student, User $author, array $data): StudentGoal
    {
        $goal = StudentGoal::firstOrNew(['student_id' => $student->id]);
        $goal->forceFill(['student_id' => $student->id] + collect($data)->only(['countries', 'degree', 'subject', 'budget', 'intake'])->map(fn ($v) => filled($v) ? trim((string) $v) : null)->all())->save();
        AuditLog::record('student.goals_saved', $student, null, null, null, $author);

        return $goal;
    }

    /** A message in the student's conversation with their counsellor. A reply from staff notifies the student. */
    public function message(User $student, User $author, string $body, ?UploadedFile $attachment = null): CounsellorMessage
    {
        // A file sent with a message is kept among the student's own documents (type Other), so the usual size, type and virus checks apply.
        $document = $attachment ? app(DocumentService::class)->store($student, $attachment, 'other', 'Sent with a message: '.Str::limit(pathinfo($attachment->getClientOriginalName(), PATHINFO_FILENAME), 80, '')) : null;
        $message = new CounsellorMessage(['body' => trim($body)]);
        $message->forceFill(['student_id' => $student->id, 'author_id' => $author->id, 'document_id' => $document?->id])->save();
        // One conversation serves every application, so the "reply needed" flag on the student's open cases follows who wrote last.
        $fromStudent = $author->id === $student->id;
        ServiceCase::where('user_id', $student->id)->whereNotIn('status', ['accepted', 'rejected', 'withdrawn'])->update(['awaiting_reply' => $fromStudent]);
        if (! $fromStudent) {
            $this->tell($student, ($author->name ?: 'Your counsellor').' sent you a message.');
        } else {
            $this->alertStaff($student, $message);
        }

        return $message;
    }

    /** Emails the student's counsellor (the assigned one, and the adviser on any of their cases); with none, the admins. */
    private function alertStaff(User $student, CounsellorMessage $message): void
    {
        $people = collect([$student->counsellor])->merge(User::whereIn('id', ServiceCase::where('user_id', $student->id)->whereNotNull('advisor_id')->pluck('advisor_id'))->get())->filter()->unique('id');
        if ($people->isEmpty()) {
            $people = User::whereHas('userRoles', fn ($q) => $q->where('role', 'administrator'))->where('status', 'active')->get();
        }
        Notification::send($people->filter(fn (User $u) => $u->isStaff() && $u->hasVerifiedEmail()), new StudentWrote($student, Str::limit($message->body, 160)));
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
