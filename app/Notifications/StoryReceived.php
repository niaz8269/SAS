<?php

namespace App\Notifications;

use App\Models\Testimonial;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Thanks to the person who sent a story: it is waiting for a review and nothing is public yet. */
class StoryReceived extends Notification
{
    public function __construct(public Testimonial $story) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('We received your story')
            ->greeting('Hello '.MailText::plain($this->story->name).',')
            ->line('Thank you for sharing your story with '.config('sas.brand.short').'. Our team reads every story before it is published, so it is not on the website yet.')
            ->line('If we publish it, it will appear with the name, country and university you gave, and your picture (or a sketch if you did not send one). We may fix small spelling mistakes. We will write to you if we need to change anything more.')
            ->line('You can ask us to remove your story at any time by replying to this email or writing to '.config('sas.contact_email').'.');
    }
}
