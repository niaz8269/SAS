<?php

namespace App\Notifications;

use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The 6-digit code a new student or agent types back to prove the address is theirs. */
class VerifyEmailCode extends Notification
{
    public function __construct(public string $code, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Your '.config('sas.brand.short').' verification code: '.$this->code)
            ->greeting('Hello '.MailText::plain($notifiable->name).',')
            ->line('Your verification code is:')
            ->line('**'.$this->code.'**')
            ->line('Type it on the page that is open in your browser. It works for '.$this->minutes.' minutes and only once.')
            ->line('If you did not create an account, ignore this email: nothing happens without the code. Never tell the code to anyone.');
    }
}
