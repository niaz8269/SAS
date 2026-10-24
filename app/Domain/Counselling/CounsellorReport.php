<?php

namespace App\Domain\Counselling;

use App\Models\ApplicationPlan;
use App\Models\CounsellorMessage;
use App\Models\StudentFollowUp;
use App\Models\User;
use Illuminate\Support\Collection;

/** Per counsellor: how many students, where they stand, how fast replies are, and how far the applications got. For managers. */
class CounsellorReport
{
    public function __construct(private Workload $workload) {}

    /** @return Collection<int,array<string,mixed>> */
    public function rows(): Collection
    {
        $counsellors = User::counsellors()->orderBy('name')->get()
            ->filter(fn (User $u) => $u->hasPermission('students.assigned'));

        return $counsellors->map(function (User $c) {
            $students = $this->workload->students($c);
            $ids = $students->pluck('id');
            $byStatus = $students->groupBy(fn ($s) => $s->latestFollowUp?->status ?? 'none')->map->count();
            $plans = ApplicationPlan::whereIn('user_id', $ids)->get();
            $work = $this->workload->for($c);

            return [
                'counsellor' => $c, 'students' => $students->count(), 'by_status' => $byStatus->all(),
                'waiting' => $work['replies']->count(), 'due' => $work['due']->count(), 'quiet' => $work['stale']->count(),
                'reply_hours' => $this->replyHours($ids->all()),
                'applications' => $plans->count(), 'submitted' => $plans->whereIn('stage', ['submitted', 'passed'])->count(), 'passed' => $plans->where('stage', 'passed')->count(),
                'offers' => $byStatus->get('offer', 0),
            ];
        })->values();
    }

    /** Average hours between a student's message and the next staff reply (replies not yet given are ignored). */
    private function replyHours(array $studentIds): ?float
    {
        $waits = [];
        CounsellorMessage::whereIn('student_id', $studentIds)->orderBy('id')->get()->groupBy('student_id')->each(function ($messages) use (&$waits) {
            $open = null;
            foreach ($messages as $m) {
                if ($m->author_id === $m->student_id) {
                    $open ??= $m->created_at;
                } elseif ($open) {
                    $waits[] = $open->diffInMinutes($m->created_at) / 60;
                    $open = null;
                }
            }
        });

        return $waits ? round(array_sum($waits) / count($waits), 1) : null;
    }

    public function statusLabels(): array
    {
        return StudentFollowUp::STATUSES + ['none' => 'Not updated yet'];
    }
}
