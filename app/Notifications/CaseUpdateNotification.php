<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A change in one of the student's cases or fees: shown in the workspace inbox and, if the student allows it, emailed. */
class CaseUpdateNotification extends Notification
{
    /** @param list<string> $channels subset of ['database', 'mail'] */
    public function __construct(public string $message, public string $url, public array $channels, public string $label = 'View your case') {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(config('sas.brand.short').': an update on your application')
            ->greeting('Hello '.$notifiable->name)
            ->line($this->message)
            ->action($this->label, $this->url)
            ->line('You can choose which updates you receive under Notifications in your workspace.');
    }

    /** @return array<string,string> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url, 'label' => $this->label];
    }
}
