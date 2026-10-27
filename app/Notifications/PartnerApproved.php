<?php

namespace App\Notifications;

use App\Models\PartnerOrganisation;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The application was approved: the person chooses their own password through this link (valid for a week). */
class PartnerApproved extends Notification
{
    public function __construct(public string $token, public PartnerOrganisation $organisation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)->subject('Your '.config('sas.brand.short').' partner account is approved')
            ->greeting('Hello '.MailText::plain($notifiable->name).',')
            ->line('Good news: '.MailText::plain($this->organisation->name).' is now a partner of '.config('sas.brand.short').'.')
            ->line('Choose your own password to open your partner (agent) account. There you can add your students, follow their applications and write to our team. The link works for 7 days.')
            ->action('Choose my password', $url)
            ->line('Then sign in at '.url('/login').'. If you did not apply, ignore this email.');
    }
}
