<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A message from SAS to a group of students, shown in the workspace inbox and emailed when the student allows service emails. */
class AnnouncementNotification extends Notification
{
    /** @param list<string> $channels */
    public function __construct(public Announcement $announcement, public array $channels) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('sas.brand.short').': '.$this->announcement->title)->greeting('Hello '.$notifiable->name)
            ->line($this->announcement->body)->action('Open your workspace', route('student.dashboard'))
            ->line('You can choose which updates you receive under Notifications in your workspace.');
    }

    /** @return array<string,string> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->announcement->title.': '.$this->announcement->body, 'url' => route('student.dashboard'), 'label' => 'Open your workspace'];
    }
}
