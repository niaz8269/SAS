<?php

namespace App\Notifications;

use App\Models\Testimonial;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells staff that a story is waiting for review. */
class StoryAlert extends Notification
{
    public function __construct(public Testimonial $story) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(config('sas.brand.short').': a new student story is waiting for review')
            ->line(MailText::plain($this->story->name).' ('.MailText::plain($this->story->placeLine()).') sent a story.')
            ->line('"'.MailText::plain($this->story->excerpt(300)).'"')
            ->line('Nothing is public until you approve it.')
            ->action('Review it', url('/admin/testimonials'));
    }
}
