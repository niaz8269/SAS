<?php

namespace App\Domain\Counselling;

use App\Domain\Scholarship\DeadlineService;
use App\Models\ApplicationPlan;
use App\Models\CounsellorMessage;
use App\Models\Student;
use App\Models\StudentFollowUp;
use App\Models\User;
use Illuminate\Support\Collection;

/** What a counsellor has to do today: replies owed, follow-ups due, students gone quiet, deadlines close and applications sent back. */
class Workload
{
    /** Students whose latest status says there is nothing left to chase. */
    private const QUIET_OK = ['closed', 'not_interested', 'on_hold'];

    public const STALE_DAYS = 7;

    public const DEADLINE_DAYS = 14;

    public function __construct(private DeadlineService $deadlines) {}

    /** @return Collection<int,Student> the counsellor's students with their current follow-up loaded */
    public function students(User $counsellor): Collection
    {
        return Student::counselledBy($counsellor)->with('latestFollowUp')->orderBy('name')->get();
    }

    /**
     * @return array{replies:Collection,due:Collection,stale:Collection,deadlines:Collection,returned:Collection,total:int}
     *                                                                                                                      replies: [student, since]; due: [student, date, followUp]; stale: [student, days]; deadlines: [student, plan, closes]; returned: [student, plan]
     */
    public function for(User $counsellor): array
    {
        $students = $this->students($counsellor);
        $ids = $students->pluck('id');

        $lastMessages = CounsellorMessage::whereIn('student_id', $ids)->orderBy('id')->get()->groupBy('student_id')->map->last();
        $replies = $students->filter(fn (Student $s) => ($m = $lastMessages->get($s->id)) && $m->author_id === $s->id)
            ->map(fn (Student $s) => ['student' => $s, 'since' => $lastMessages->get($s->id)->created_at])->sortBy('since')->values();

        $today = today();
        $due = $students->filter(fn (Student $s) => $s->latestFollowUp?->follow_up_on && $s->latestFollowUp->follow_up_on->lte($today))
            ->map(fn (Student $s) => ['student' => $s, 'date' => $s->latestFollowUp->follow_up_on, 'followUp' => $s->latestFollowUp])->sortBy('date')->values();

        $stale = $students->reject(fn (Student $s) => in_array($s->latestFollowUp?->status, self::QUIET_OK, true) || $due->contains(fn ($d) => $d['student']->id === $s->id))
            ->map(function (Student $s) {
                $last = $s->latestFollowUp?->created_at ?? $s->created_at;

                return ['student' => $s, 'days' => (int) $last->diffInDays(now()), 'never' => $s->latestFollowUp === null];
            })->filter(fn ($row) => $row['days'] >= ($row['never'] ? 3 : self::STALE_DAYS))->sortByDesc('days')->values();

        $plans = ApplicationPlan::whereIn('user_id', $ids)->whereIn('stage', ['draft', 'returned'])->with(['cycle.programme', 'user'])->get();
        $deadlines = $plans->map(fn (ApplicationPlan $p) => ['student' => $students->firstWhere('id', $p->user_id), 'plan' => $p, 'closes' => $this->deadlines->nextClosing($p->cycle)])
            ->filter(fn ($row) => $row['student'] && $row['closes'] && $row['closes']->between(now(), now()->addDays(self::DEADLINE_DAYS)))->sortBy('closes')->values();
        $returned = $plans->where('stage', 'returned')->map(fn (ApplicationPlan $p) => ['student' => $students->firstWhere('id', $p->user_id), 'plan' => $p])->filter(fn ($row) => $row['student'])->values();

        return [
            'replies' => $replies, 'due' => $due, 'stale' => $stale, 'deadlines' => $deadlines, 'returned' => $returned,
            'total' => $replies->count() + $due->count() + $stale->count() + $deadlines->count() + $returned->count(),
        ];
    }

    /** Students of this counsellor whose latest status is the given one (for the status count on the dashboard). */
    public function byStatus(User $counsellor): array
    {
        return $this->students($counsellor)->groupBy(fn (Student $s) => $s->latestFollowUp?->status ?? 'none')->map->count()->all();
    }

    public function statusLabel(string $key): string
    {
        return $key === 'none' ? 'Not updated yet' : (StudentFollowUp::STATUSES[$key] ?? $key);
    }
}
