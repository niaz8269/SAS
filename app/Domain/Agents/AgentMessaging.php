<?php

namespace App\Domain\Agents;

use App\Domain\Editorial\AuditLog;
use App\Models\AgentMessage;
use App\Models\ServiceCase;
use App\Models\User;
use App\Notifications\AgentWrote;
use App\Notifications\SasWroteToAgent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** What an agency and SAS say to each other about one student. The student does not see it. */
class AgentMessaging
{
    /** An agent writes about a student their agency looks after. SAS (the student's counsellor, or the admins) is told by email. */
    public function fromAgent(User $student, User $agent, string $body): AgentMessage
    {
        if (! $agent->manages($student)) {
            throw new InvalidArgumentException('That student is not looked after by your agency.');
        }
        $message = $this->store($student, $agent, true, $body);
        $people = collect([$student->counsellor])->merge(User::whereIn('id', ServiceCase::where('user_id', $student->id)->whereNotNull('advisor_id')->pluck('advisor_id'))->get())->filter()->unique('id');
        if ($people->isEmpty()) {
            $people = User::whereHas('userRoles', fn ($q) => $q->where('role', 'administrator'))->where('status', 'active')->get();
        }
        Notification::send($people->filter(fn (User $u) => $u->isStaff() && $u->hasVerifiedEmail()), new AgentWrote($student, $agent, Str::limit($message->body, 160)));

        return $message;
    }

    /** SAS staff answer the agency. The agency's people are told by email. */
    public function fromStaff(User $student, User $staff, string $body): AgentMessage
    {
        if (! $student->managed_by_organisation_id) {
            throw new InvalidArgumentException('This student has no agent.');
        }
        $message = $this->store($student, $staff, false, $body);
        $people = User::where('partner_organisation_id', $student->managed_by_organisation_id)->where('status', 'active')->whereNotNull('email_verified_at')->get();
        Notification::send($people, new SasWroteToAgent($student, $staff, Str::limit($message->body, 160)));

        return $message;
    }

    private function store(User $student, User $author, bool $fromAgent, string $body): AgentMessage
    {
        $message = new AgentMessage(['body' => trim($body)]);
        $message->forceFill(['student_id' => $student->id, 'organisation_id' => $student->managed_by_organisation_id, 'author_id' => $author->id, 'from_agent' => $fromAgent])->save();
        AuditLog::record($fromAgent ? 'agent.message_sent' : 'agent.message_answered', $student, null, null, null, $author);

        return $message;
    }

    /** Whether the newest message about this student is from the agency, so SAS still owes an answer. */
    public static function awaitingAnswer(User $student): bool
    {
        return AgentMessage::where('student_id', $student->id)->latest('id')->value('from_agent') === true;
    }
}
