<?php

namespace App\Notifications;

use App\Models\ApplicationPlan;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the student (and the agent who looks after them) the result of the SAS review: returned with remarks, or passed. */
class ApplicationSentBack extends Notification
{
    public function __construct(public ApplicationPlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->plan->loadMissing('cycle.programme', 'user');
        $returned = $plan->stage === 'returned';
        $mail = (new MailMessage)->subject($returned ? 'SAS: your application needs changes' : 'SAS: your application passed review')
            ->line('Application '.$plan->application_no.' for '.$plan->cycle->displayName().' ('.$plan->user->name.').');
        $returned
            ? $mail->line('SAS returned it. Please fix the following and send it again:')->line($plan->review_remarks)
            : $mail->line('SAS reviewed it and it passed. SAS will tell you the next step.')->when($plan->review_remarks, fn ($m) => $m->line($plan->review_remarks));

        return $mail->line('Sign in to the SAS workspace to continue.');
    }
}
