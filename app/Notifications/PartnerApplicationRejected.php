<?php

namespace App\Notifications;

use App\Models\PartnerApplication;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The application was not approved. The reason, if staff wrote one, is passed on kindly. */
class PartnerApplicationRejected extends Notification
{
    public function __construct(public PartnerApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('About your partner application')
            ->greeting('Hello '.MailText::plain($this->application->applicant_name).',')
            ->line('Thank you for your interest in becoming a partner of '.config('sas.brand.short').'. We are sorry that we cannot approve the application of '.MailText::plain($this->application->agency_name).' at this time.');
        if (filled($this->application->review_note)) {
            $mail->line('Our note: '.MailText::plain($this->application->review_note));
        }

        return $mail->line('You are welcome to apply again later or to write to us at '.config('sas.contact_email').'.');
    }
}
