<?php

namespace App\Domain\StudentWorkspace;

use App\Domain\Applications\ApplicationFormService;
use App\Domain\Documents\Requirements;
use App\Domain\Editorial\AuditLog;
use App\Models\ApplicationPlan;
use App\Models\User;
use App\Notifications\ApplicationSentBack;
use App\Notifications\ApplicationSubmittedAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * The last step of an application, Send to SAS. The student (or the agent for them) sends the finished application to SAS staff, who either
 * return it with remarks to fix or pass it. Sending is allowed only when nothing is missing. Nothing is charged here.
 */
class ApplicationReview
{
    public function __construct(private Requirements $requirements, private ApplicationFormService $forms, private StudyLanguages $languages) {}

    /** @return list<string> what still stands in the way of sending the application; empty means it can be sent */
    public function problems(ApplicationPlan $plan): array
    {
        if (! $plan->isEditable()) {
            return ['This application is already with SAS.'];
        }

        return array_values(array_filter($this->parts($plan)));
    }

    /**
     * What is missing in each part of an application, by page: 'level' and 'language' (Degree and language), 'details' (Your details) and 'documents' (Documents).
     * A part that is complete is null. The pages use this to tick only what is really done, and problems() to say what stops the sending.
     *
     * @return array{level: ?string, language: ?string, details: ?string, documents: ?string}
     */
    public function parts(ApplicationPlan $plan): array
    {
        $plan->loadMissing('cycle.degreeLevels', 'cycle.destinations', 'cycle.programme.hostCountry');
        $missing = $this->forms->missing($this->forms->formFor($plan->user));
        $docs = $this->requirements->progress($this->requirements->forPlan($plan));

        return [
            'level' => $plan->cycle->degreeLevels->count() > 1 && ! $plan->degree_level_id ? 'Choose the degree you are applying for (Degree and language).' : null,
            'language' => count($this->languages->optionsFor($plan->cycle)) > 1 && ! $plan->study_language ? 'Choose the language you want to study in (Degree and language).' : null,
            'details' => $missing ? 'Your details are not complete: '.implode(', ', array_slice($missing, 0, 6)).(count($missing) > 6 ? ' and '.(count($missing) - 6).' more' : '').'.' : null,
            'documents' => $docs['missing'] ? 'Mandatory documents are missing (Documents): '.implode(', ', $docs['missing']).'. Upload each one, or tick "I cannot provide it now" and write the reason.' : null,
        ];
    }

    public function submit(ApplicationPlan $plan, User $actor): ApplicationPlan
    {
        $problems = $this->problems($plan);
        if ($problems) {
            throw new InvalidArgumentException(implode(' ', $problems));
        }
        DB::transaction(function () use ($plan, $actor) {
            $plan->forceFill(['stage' => 'submitted', 'review_requested_at' => now(), 'review_remarks' => null])->save();
            AuditLog::record('plan.submitted_for_review', $plan, null, ['by' => $actor->id], null, $actor);
        });
        if ($staff = config('sas.staff_notification_email')) {
            Notification::route('mail', $staff)->notify(new ApplicationSubmittedAlert($plan));
        }

        return $plan;
    }

    /** Staff send the application back with remarks, or pass it. The student and any agent are told. */
    public function review(ApplicationPlan $plan, string $decision, ?string $remarks, User $staff): ApplicationPlan
    {
        if ($plan->stage !== 'submitted') {
            throw new InvalidArgumentException('Only an application waiting for review can be reviewed.');
        }
        if ($decision === 'return' && blank($remarks)) {
            throw new InvalidArgumentException('Write what the student needs to fix.');
        }
        $stage = $decision === 'return' ? 'returned' : 'passed';
        DB::transaction(function () use ($plan, $stage, $remarks, $staff) {
            $plan->forceFill(['stage' => $stage, 'review_remarks' => $remarks ? trim($remarks) : null, 'reviewed_at' => now(), 'reviewed_by' => $staff->id])->save();
            AuditLog::record('plan.reviewed', $plan, null, ['stage' => $stage], null, $staff);
        });
        $student = $plan->user;
        $agentUsers = $student->managed_by_organisation_id ? User::where('partner_organisation_id', $student->managed_by_organisation_id)->where('status', 'active')->get() : collect();
        $notification = new ApplicationSentBack($plan);
        $student->email_verified_at ? $student->notify($notification) : null;
        Notification::send($agentUsers, $notification);

        return $plan;
    }
}
