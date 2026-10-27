<?php

namespace App\Domain\Agents;

use App\Domain\Editorial\AuditLog;
use App\Models\Country;
use App\Models\PartnerApplication;
use App\Models\PartnerOrganisation;
use App\Models\User;
use App\Notifications\PartnerApplicationRejected;
use App\Notifications\PartnerApproved;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Approving or rejecting an agency's application. Approval is the only way a partner (agent) account comes to exist. */
class PartnerApplications
{
    /** @return array{organisation:PartnerOrganisation,user:User,mailed:bool} */
    public function approve(PartnerApplication $application, User $by): array
    {
        $email = mb_strtolower(trim($application->applicant_email));
        [$organisation, $user] = DB::transaction(function () use ($application, $by, $email) {
            $application = PartnerApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') {
                throw new DomainException('This application was already '.$application->status.'.');
            }
            if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                throw new DomainException('Someone already has an account with '.$email.'. Ask the applicant to use another email address.');
            }

            $organisation = new PartnerOrganisation([
                'name' => $application->agency_name,
                'country_id' => Country::where('name', $application->agency_country)->value('id'),
                'website' => $application->website,
                'contact_email' => $application->agency_email ?: $email,
                'internal_note' => 'Applied through the website on '.($application->submitted_at ?? $application->created_at)->toDateString().'. Registration no.: '.($application->registration_number ?: 'not given').'.',
            ]);
            $organisation->forceFill(['status' => 'active'])->save();

            $user = new User(['name' => $application->applicant_name, 'email' => $email, 'password' => Str::random(40)]);
            $user->forceFill(['phone' => $application->applicant_phone, 'partner_organisation_id' => $organisation->getKey(), 'status' => 'active', 'email_verified_at' => now()])->save();
            $user->userRoles()->create(['role' => 'partner_manager', 'granted_by' => $by->getKey()]);

            $application->forceFill(['status' => 'approved', 'reviewed_by' => $by->getKey(), 'reviewed_at' => now(), 'partner_organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey()])->save();
            AuditLog::record('partner.application_approved', $application, null, ['organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey()]);

            return [$organisation, $user];
        });

        // The person chooses their own password from this link (valid for a week). If email is down the account still exists: staff can resend from the person's page.
        $mailed = true;
        try {
            $token = Password::broker('invites')->createToken($user);
            $user->notify(new PartnerApproved($token, $organisation));
        } catch (\Throwable $e) {
            report($e);
            $mailed = false;
        }

        return ['organisation' => $organisation, 'user' => $user, 'mailed' => $mailed];
    }

    public function reject(PartnerApplication $application, User $by, ?string $note): void
    {
        DB::transaction(function () use ($application, $by, $note) {
            $application = PartnerApplication::whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') {
                throw new DomainException('This application was already '.$application->status.'.');
            }
            $application->forceFill(['status' => 'rejected', 'review_note' => filled($note) ? trim($note) : null, 'reviewed_by' => $by->getKey(), 'reviewed_at' => now()])->save();
            AuditLog::record('partner.application_rejected', $application);
        });

        try {
            Notification::route('mail', $application->applicant_email)->notify(new PartnerApplicationRejected($application->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
