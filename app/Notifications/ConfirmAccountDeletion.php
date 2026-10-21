<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmAccountDeletion extends Notification
{
    public function __construct(private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirm deletion of your account')
            ->line('You asked to delete your account and uploaded documents. This cannot be undone.')
            ->action('Review and confirm', $this->url)
            ->line('The link works once and expires in 24 hours. If this was not you, ignore this email and change your password.');
    }
}
