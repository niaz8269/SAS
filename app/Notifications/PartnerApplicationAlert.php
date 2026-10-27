<?php

namespace App\Notifications;

use App\Models\PartnerApplication;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells staff that an agency applied and waits for approval. */
class PartnerApplicationAlert extends Notification
{
    public function __construct(public PartnerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->application;

        return (new MailMessage)->subject(config('sas.brand.short').': a new partner application is waiting')
            ->line(MailText::plain($a->agency_name).' ('.MailText::plain($a->agency_country).') applied to become a partner.')
            ->line('Contact: '.MailText::plain($a->applicant_name).', '.MailText::plain($a->applicant_position).'.')
            ->line('No account exists until you approve it.')
            ->action('Review it', url('/admin/partner-applications'));
    }
}
