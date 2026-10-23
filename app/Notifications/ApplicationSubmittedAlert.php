<?php

namespace App\Notifications;

use App\Models\ApplicationPlan;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells SAS staff an application is waiting for review. */
class ApplicationSubmittedAlert extends Notification
{
    public function __construct(public ApplicationPlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->plan->loadMissing('cycle.programme', 'user');

        return (new MailMessage)->subject('SAS: an application is waiting for review')
            ->line($plan->user->name.' applied for '.$plan->cycle->displayName().' ('.$plan->application_no.').')
            ->line('Open Service desk > Applications in the admin to return it with remarks or pass it.');
    }
}
