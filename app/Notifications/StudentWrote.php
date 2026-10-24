<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a counsellor (or the admins, when the student has none) that a student wrote to them. */
class StudentWrote extends Notification
{
    public function __construct(public User $student, public string $excerpt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('sas.brand.short').': '.$this->student->name.' sent you a message')
            ->line(MailText::plain($this->student->name).' wrote: "'.MailText::plain($this->excerpt).'"')
            ->line('Open Service desk > Students in the admin to reply.')
            ->action('Open Students', url('/admin/students/'.$this->student->id));
    }
}
