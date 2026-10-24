<?php

namespace App\Domain\Admin;

use App\Enums\EnquiryStatus;
use App\Models\ApplicationPlan;
use App\Models\CounsellorMessage;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\ServiceCase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What an admin has to chase today, in one place: unassigned work, anything that has waited longer than the limits in
 * config/sas.php (sla), and money that is overdue. Each group has a key, a title, a hint on what to do, and rows of [label, detail, model].
 */
class Desk
{
    private const OPEN_CASE = ['new', 'in_progress', 'submitted'];

    /** @return array<string,array{title:string,hint:string,late:bool,rows:Collection}> keyed by group; "late" groups count towards the alert email */
    public function groups(): array
    {
        $sla = config('sas.sla');
        $now = now();

        $openEnquiry = Enquiry::whereNotIn('status', [EnquiryStatus::Closed->value, EnquiryStatus::Converted->value]);
        $unassignedEnquiries = (clone $openEnquiry)->whereNull('owner_id')->oldest()->limit(100)->get();
        $slowEnquiries = (clone $openEnquiry)->whereNull('first_response_at')->where('created_at', '<', $now->copy()->subHours($sla['enquiry_hours']))->oldest()->limit(100)->get();

        $unassignedCases = ServiceCase::whereNull('advisor_id')->whereIn('status', self::OPEN_CASE)->with('student')->oldest('opened_at')->limit(100)->get();
        $unassignedStudents = Student::whereNull('counsellor_id')->whereNotIn('users.id', ServiceCase::whereNotNull('advisor_id')->select('user_id'))->latest()->limit(100)->get();

        $review = ApplicationPlan::where('stage', 'submitted')->with(['user', 'cycle.programme'])->orderBy('review_requested_at')->limit(100)->get();
        $returned = ApplicationPlan::where('stage', 'returned')->where('reviewed_at', '<', $now->copy()->subDays($sla['returned_days']))->with(['user', 'cycle.programme'])->orderBy('reviewed_at')->limit(100)->get();

        $unanswered = $this->unansweredMessages($now->copy()->subHours($sla['reply_hours']));

        $unpaid = Invoice::where('kind', 'agency_charge')->where('status', 'issued')->where('issued_at', '<', $now->copy()->subDays($sla['unpaid_days']))->with(['serviceCase', 'student'])->orderBy('issued_at')->limit(100)->get();
        $refunds = Invoice::where('refund_status', 'due')->with(['serviceCase', 'student'])->orderBy('refunded_at')->limit(100)->get();

        return [
            'slow_enquiries' => ['title' => 'Enquiries with no reply for '.$sla['enquiry_hours'].' hours', 'hint' => 'Open the enquiry and answer it or give it an owner.', 'late' => true, 'rows' => $slowEnquiries],
            'unassigned_enquiries' => ['title' => 'Enquiries without an owner', 'hint' => 'Give each one to a counsellor.', 'late' => false, 'rows' => $unassignedEnquiries],
            'unanswered' => ['title' => 'Students waiting more than '.$sla['reply_hours'].' hours for a reply', 'hint' => 'Their counsellor has not answered. Nudge them or take it over.', 'late' => true, 'rows' => $unanswered],
            'review' => ['title' => 'Applications waiting for SAS review', 'hint' => 'Return with remarks or pass. Older than '.$sla['review_days'].' days counts as late.', 'late' => false, 'rows' => $review],
            'returned' => ['title' => 'Returned more than '.$sla['returned_days'].' days ago and still not fixed', 'hint' => 'Ask the student or agent, or their counsellor, to fix it.', 'late' => true, 'rows' => $returned],
            'unassigned_cases' => ['title' => 'Cases without a counsellor', 'hint' => 'Assign one so somebody owns the case.', 'late' => false, 'rows' => $unassignedCases],
            'unassigned_students' => ['title' => 'Students without a counsellor', 'hint' => 'Assign one, or use "Assign to the least busy" on the Students list.', 'late' => false, 'rows' => $unassignedStudents],
            'unpaid' => ['title' => 'Agency charge unpaid for '.$sla['unpaid_days'].' days', 'hint' => 'The student was accepted and the service charge is still open.', 'late' => true, 'rows' => $unpaid],
            'refunds' => ['title' => 'Refunds due', 'hint' => 'Record each refund once it is paid.', 'late' => false, 'rows' => $refunds],
        ];
    }

    /** Whether a review waiting since this date is late. */
    public function reviewIsLate(ApplicationPlan $plan): bool
    {
        return $plan->review_requested_at !== null && $plan->review_requested_at->lt(now()->subDays(config('sas.sla.review_days')));
    }

    /** Applications waiting for review longer than the limit, merged into the late count. */
    public function lateReviews(): Collection
    {
        return ApplicationPlan::where('stage', 'submitted')->where('review_requested_at', '<', now()->subDays(config('sas.sla.review_days')))->with(['user', 'cycle.programme'])->orderBy('review_requested_at')->get();
    }

    /** @return int how many things are late (the number the alert email and the dashboard badge use) */
    public function lateCount(?array $groups = null): int
    {
        $groups ??= $this->groups();

        return collect($groups)->filter(fn ($g) => $g['late'])->sum(fn ($g) => $g['rows']->count()) + $this->lateReviews()->count();
    }

    public function total(?array $groups = null): int
    {
        return collect($groups ?? $this->groups())->sum(fn ($g) => $g['rows']->count());
    }

    /** Students whose newest message is their own and older than the cut-off. */
    private function unansweredMessages(Carbon $before): Collection
    {
        $last = CounsellorMessage::whereIn('id', CounsellorMessage::query()->selectRaw('max(id)')->groupBy('student_id'))->get();
        $ids = $last->filter(fn ($m) => $m->author_id === $m->student_id && $m->created_at->lt($before))->pluck('student_id');

        return Student::whereIn('users.id', $ids)->with('counsellor')->get()->each(fn ($s) => $s->setAttribute('waiting_since', $last->firstWhere('student_id', $s->id)->created_at))->sortBy('waiting_since')->values();
    }

    /**
     * How busy each counsellor is. score = students + open cases + open enquiries + twice the replies owed, so the least busy is the one to give new work to.
     *
     * @return Collection<int,array{counsellor:User,students:int,cases:int,enquiries:int,replies:int,score:int}>
     */
    public function workload(): Collection
    {
        $last = CounsellorMessage::whereIn('id', CounsellorMessage::query()->selectRaw('max(id)')->groupBy('student_id'))->get();

        return User::counsellors()->orderBy('name')->get()->map(function (User $c) use ($last) {
            $students = Student::counselledBy($c)->pluck('users.id');
            $cases = ServiceCase::where('advisor_id', $c->id)->whereIn('status', self::OPEN_CASE)->count();
            $enquiries = Enquiry::where('owner_id', $c->id)->whereNotIn('status', [EnquiryStatus::Closed->value, EnquiryStatus::Converted->value])->count();
            $replies = $last->filter(fn ($m) => $students->contains($m->student_id) && $m->author_id === $m->student_id)->count();

            return ['counsellor' => $c, 'students' => $students->count(), 'cases' => $cases, 'enquiries' => $enquiries, 'replies' => $replies, 'score' => $students->count() + $cases + $enquiries + 2 * $replies];
        })->values();
    }

    /** The active counsellor, admin or super admin with the lowest load; ties go to the first by name. */
    public function leastBusy(): ?User
    {
        return $this->workload()->sortBy('score')->first()['counsellor'] ?? null;
    }
}
