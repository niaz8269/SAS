<?php

namespace App\Domain\StudentWorkspace;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\Editorial\AuditLog;
use App\Enums\EnquiryStatus;
use App\Mail\StaffEnquiryAlert;
use App\Models\ApplicationPlan;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * "I am ready: please review my application." It goes to SAS staff as an ordinary enquiry (no fee, no commitment),
 * so the student gets a reference and the usual reply channel. It is allowed once every mandatory document is provided.
 */
class ApplicationReview
{
    public function __construct(private Requirements $requirements, private ApplicationFormService $forms) {}

    public function request(ApplicationPlan $plan, User $student): Enquiry
    {
        if ($plan->user_id !== $student->id) {
            throw new InvalidArgumentException('This is not your application.');
        }
        if ($plan->review_requested_at) {
            throw new InvalidArgumentException('You already asked SAS to review this application.');
        }
        $plan->loadMissing('cycle.programme', 'cycle.degreeLevels');
        $progress = $this->requirements->progress($this->requirements->forScholarship($plan->cycle, $plan->degree_level_id, $student->id));
        if ($progress['missing']) {
            throw new InvalidArgumentException('Upload the missing mandatory documents first: '.implode(', ', $progress['missing']).'.');
        }
        $percent = $this->forms->percent($this->forms->formFor($student));
        $level = $plan->degreeLevel?->name ?? ($plan->cycle->degreeLevels->count() === 1 ? $plan->cycle->degreeLevels->first()->name : 'not chosen');

        return DB::transaction(function () use ($plan, $student, $progress, $percent, $level) {
            $enquiry = new Enquiry([
                'category' => 'advisory', 'name' => $student->name, 'email' => $student->email, 'phone' => $student->phone, 'contact_preference' => 'email',
                'cycle_id' => $plan->cycle_id,
                'message' => 'Please review my application for '.$plan->cycle->displayName().' (degree: '.$level.'). All '.$progress['required'].' mandatory documents are uploaded and my application form is '.$percent.'% complete. Sent from my SAS workspace.',
                'consent_text' => 'I asked SAS to review my application and agree that SAS may contact me by email about it.', 'consent_at' => now(),
                'view_token_hash' => hash('sha256', Str::random(40)), 'view_token_expires_at' => now()->addDays(30),
            ]);
            $enquiry->user_id = $student->id;
            $enquiry->status = EnquiryStatus::New;
            $enquiry->save();

            $plan->forceFill(['review_requested_at' => now(), 'review_enquiry_id' => $enquiry->id])->save();
            AuditLog::record('plan.review_requested', $plan, null, ['enquiry' => $enquiry->reference], null, $student);
            if ($staff = config('sas.staff_notification_email')) {
                Mail::to($staff)->queue(new StaffEnquiryAlert($enquiry));
            }

            return $enquiry;
        });
    }
}
