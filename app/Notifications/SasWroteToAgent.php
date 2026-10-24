<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a recruiting agency's people that SAS answered about one of their students. */
class SasWroteToAgent extends Notification
{
    public function __construct(public User $student, public User $staff, public string $excerpt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('sas.brand.short').': an answer about '.$this->student->name)->greeting('Hello '.MailText::plain($notifiable->name))
            ->line(MailText::plain($this->staff->name.' at SAS wrote about '.$this->student->name).': "'.MailText::plain($this->excerpt).'"')
            ->action('Open the student', route('partner.student.show', $this->student->public_id));
    }
}
