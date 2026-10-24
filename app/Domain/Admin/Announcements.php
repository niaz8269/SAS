<?php

namespace App\Domain\Admin;

use App\Domain\Communication\Preferences;
use App\Domain\Editorial\AuditLog;
use App\Domain\Journey\Journey;
use App\Domain\Scholarship\DeadlineService;
use App\Models\Announcement;
use App\Models\ApplicationPlan;
use App\Models\PartnerOrganisation;
use App\Models\ScholarshipProgramme;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Builds the group of students an announcement is for, and sends it once. */
class Announcements
{
    public function __construct(private DeadlineService $deadlines, private Journey $journey) {}

    /** @return Collection<int,Student> active students in the audience */
    public function recipients(Announcement $a): Collection
    {
        $value = $a->audience_value;
        $query = Student::where('users.status', 'active');

        return match ($a->audience) {
            'all' => $query->get(),
            'programme' => $query->whereIn('users.id', ApplicationPlan::whereHas('cycle', fn ($c) => $c->where('programme_id', (int) $value))->select('user_id'))->get(),
            'agent' => $query->where('managed_by_organisation_id', (int) $value)->get(),
            'counsellor' => $query->counselledBy(User::findOrFail((int) $value))->get(),
            'journey' => $query->whereIn('users.id', ApplicationPlan::where('stage', 'passed')->where('journey_stage', (string) $value)->select('user_id'))->get(),
            'deadline' => $this->closingSoon($query, max(1, (int) $value)),
            default => throw new InvalidArgumentException('Unknown audience.'),
        };
    }

    /** Students with an application still being written whose scholarship closes within the number of days. */
    private function closingSoon($query, int $days): Collection
    {
        $ids = ApplicationPlan::whereIn('stage', ['draft', 'returned'])->with('cycle')->get()
            ->filter(fn (ApplicationPlan $p) => ($closes = $this->deadlines->nextClosing($p->cycle)) && $closes->between(now(), now()->addDays($days)))->pluck('user_id')->unique();

        return $query->whereIn('users.id', $ids)->get();
    }

    public function describe(Announcement $a): string
    {
        return match ($a->audience) {
            'programme' => 'applied to '.(ScholarshipProgramme::find((int) $a->audience_value)?->title ?? 'a scholarship'),
            'deadline' => 'with an unfinished application closing within '.max(1, (int) $a->audience_value).' days',
            'agent' => 'of '.(PartnerOrganisation::find((int) $a->audience_value)?->name ?? 'an agency'),
            'counsellor' => 'of '.(User::find((int) $a->audience_value)?->name ?? 'a counsellor'),
            'journey' => 'whose application is at "'.$this->journey->label((string) $a->audience_value).'"',
            default => 'every student',
        };
    }

    /** Sends the announcement once. Each student gets it in the workspace inbox and by email when their preferences allow service emails. */
    public function send(Announcement $a, User $actor): int
    {
        if ($a->isSent()) {
            throw new InvalidArgumentException('This announcement was already sent.');
        }
        $sent = 0;
        foreach ($this->recipients($a) as $student) {
            Preferences::ensureDefaults($student);
            $channels = [];
            Preferences::enabled($student, 'transactional', 'in_app') && $channels[] = 'database';
            Preferences::enabled($student, 'transactional', 'mail') && $student->hasVerifiedEmail() && $channels[] = 'mail';
            if ($channels !== []) {
                $student->notify(new AnnouncementNotification($a, $channels));
                $sent++;
            }
        }
        $a->forceFill(['sent_at' => now(), 'recipients' => $sent])->save();
        AuditLog::record('announcement.sent', $a, null, ['recipients' => $sent, 'audience' => $a->audience], null, $actor);

        return $sent;
    }
}
