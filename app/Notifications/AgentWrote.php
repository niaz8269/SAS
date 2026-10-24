<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells SAS staff that a recruiting agent wrote about one of their students. */
class AgentWrote extends Notification
{
    public function __construct(public User $student, public User $agent, public string $excerpt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('sas.brand.short').': '.$this->agent->name.' wrote about '.$this->student->name)
            ->line(MailText::plain($this->agent->name.' ('.($this->agent->partnerOrganisation?->name ?? 'agency').') wrote about '.$this->student->name).': "'.MailText::plain($this->excerpt).'"')
            ->line('Open the student in the admin, then the Agent messages tab, to answer.')
            ->action('Open the student', url('/admin/students/'.$this->student->id));
    }
}
