<?php

namespace App\Domain\Admin;

use App\Domain\Cases\CaseService;
use App\Domain\Counselling\Counselling;
use App\Domain\Editorial\AuditLog;
use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\ScholarshipCycle;
use App\Models\ServiceCase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Turns an enquiry into a SAS case for the person who wrote it, in one step. */
class EnquiryConversion
{
    public function __construct(private CaseService $cases, private Counselling $counselling, private Desk $desk) {}

    /** The student account the enquiry came from: the signed-in account that sent it, or the one with the same email. */
    public function studentFor(Enquiry $enquiry): ?Student
    {
        return Student::find($enquiry->user_id) ?? Student::where('email', $enquiry->email)->first();
    }

    public function convert(Enquiry $enquiry, ?ScholarshipCycle $cycle, ?User $advisor, ?string $note, User $actor): ServiceCase
    {
        if ($enquiry->converted_case_id) {
            throw new InvalidArgumentException('This enquiry already became a case.');
        }
        $student = $this->studentFor($enquiry) ?? throw new InvalidArgumentException('Nobody with the email '.$enquiry->email.' has a SAS account yet. Ask them to register, then turn this enquiry into a case.');
        $advisor ??= $enquiry->owner ?? $this->desk->leastBusy();

        return DB::transaction(function () use ($enquiry, $student, $cycle, $advisor, $note, $actor) {
            $case = $this->cases->open($student, $cycle, $advisor, [
                'title' => $cycle ? null : 'Enquiry '.$enquiry->reference,
                'internal_note' => trim('From enquiry '.$enquiry->reference.".\n".$enquiry->message."\n".($note ?? '')),
            ], $actor);
            if ($advisor && ! $student->counsellor_id) {
                $this->counselling->assign($student, $advisor, $actor);
            }
            $before = $enquiry->only(['status', 'owner_id']);
            $enquiry->forceFill(['status' => EnquiryStatus::Converted, 'converted_case_id' => $case->id, 'owner_id' => $enquiry->owner_id ?? $advisor?->id])->save();
            AuditLog::record('enquiry.converted', $enquiry, $before, ['case' => $case->id], null, $actor);

            return $case;
        });
    }
}
