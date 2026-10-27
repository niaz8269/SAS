<?php

namespace App\Notifications;

use App\Models\PartnerApplication;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Thanks to the agency that applied: the application is waiting for a review and no account exists yet. */
class PartnerApplicationReceived extends Notification
{
    public function __construct(public PartnerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('We received your partner application')
            ->greeting('Hello '.MailText::plain($this->application->applicant_name).',')
            ->line('Thank you for applying to become a partner of '.config('sas.brand.short').' with '.MailText::plain($this->application->agency_name).'.')
            ->line('Our team reviews every application. If it is approved, we email you a link to choose your own password and open your partner (agent) account. If we need anything more, we will write to you at this address.')
            ->line('Nothing is needed from you now. Questions: '.config('sas.contact_email').'.');
    }
}
