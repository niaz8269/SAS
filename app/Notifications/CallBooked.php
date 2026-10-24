<?php

namespace App\Notifications;

use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a counsellor that a student booked one of their free times. */
class CallBooked extends Notification
{
    public function __construct(public User $student, public AvailabilitySlot $slot, public ?string $note = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->slot->starts_at->copy()->timezone($notifiable->timezone ?: 'UTC')->format('l j F Y, H:i').' ('.($notifiable->timezone ?: 'UTC').')';
        $mail = (new MailMessage)->subject(config('sas.brand.short').': '.$this->student->name.' booked a call')->line($this->student->name.' booked a '.$this->slot->duration_minutes.'-minute call with you on '.$when.'.');
        if ($this->note) {
            $mail->line('Their note: "'.$this->note.'"');
        }

        return $mail->action('Open My calendar', url('/admin/my-calendar'));
    }
}
