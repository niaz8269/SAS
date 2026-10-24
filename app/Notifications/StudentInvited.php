<?php

namespace App\Notifications;

use App\Models\PartnerOrganisation;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A recruiting agent added the student; the student chooses their own password through this link (valid for a week). */
class StudentInvited extends Notification
{
    public function __construct(public string $token, public PartnerOrganisation $agent) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)->subject('Your '.config('sas.brand.short').' account')
            ->greeting('Hello '.MailText::plain($notifiable->name).',')
            ->line(MailText::plain($this->agent->name).' set up a '.config('sas.brand.short').' account for you so they can prepare your scholarship applications with you.')
            ->line('Choose your own password to sign in, check your details and follow your applications. The link works for 7 days.')
            ->action('Choose my password', $url)
            ->line('If you did not expect this, you can ignore this email: nothing happens until you choose a password.');
    }
}
