<?php

namespace App\Notifications;

use App\Domain\Scholarship\DeadlineService;
use App\Models\CycleDeadline;
use App\Models\NotificationEvent;
use App\Models\ScholarshipCycle;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeadlineReminderNotification extends Notification
{
    /** @param list<string> $channels subset of ['mail', 'database'] */
    public function __construct(
        public NotificationEvent $event,
        public ScholarshipCycle $cycle,
        public CycleDeadline $deadline,
        public array $channels,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    private function wording(): string
    {
        $d = app(DeadlineService::class)->describe($this->deadline, $this->event->user->timezone ?? null);
        $title = $this->cycle->label();

        return $d['confirmed_time']
            ? "{$title} closes on {$d['primary']}."
            : "{$title} closes on {$d['primary']} ({$d['note']}). Check the official page for the exact time.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reminder: {$this->cycle->label()} closes in {$this->event->days_before} day(s)")
            ->greeting('Hello '.$notifiable->name)
            ->line($this->wording())
            ->line('Applications are made on the official website. SAS does not submit applications for you.')
            ->action('View scholarship', route('scholarships.show', [$this->cycle->programme->slug, $this->cycle->slug]))
            ->line('You can change reminder preferences in your account at any time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'message' => $this->wording(),
            'cycle_id' => $this->cycle->id,
            'url' => route('scholarships.show', [$this->cycle->programme->slug, $this->cycle->slug]),
        ];
    }
}
