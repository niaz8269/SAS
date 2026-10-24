<?php

namespace App\Notifications;

use App\Domain\Admin\DeskRows;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** The admins' morning email: only what is late (slow enquiries, unanswered students, reviews and fixes that waited too long, unpaid charges). */
class AdminAlertDigest extends Notification
{
    /** @param array<string,array{title:string,hint:string,late:bool,rows:Collection}> $groups  @param \Illuminate\Support\Collection $lateReviews */
    public function __construct(public array $groups, public $lateReviews) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $late = collect($this->groups)->filter(fn ($g) => $g['late'] && $g['rows']->isNotEmpty());
        $count = $late->sum(fn ($g) => $g['rows']->count()) + $this->lateReviews->count();
        $mail = (new MailMessage)->subject(config('sas.brand.short').': '.$count.' late '.str('item')->plural($count).' need an admin')->greeting('Good morning '.$notifiable->name);
        if ($this->lateReviews->isNotEmpty()) {
            $mail->line('**Applications waiting more than '.config('sas.sla.review_days').' days for review**')
                ->line($this->lateReviews->take(15)->map(fn ($p) => '- '.$p->application_no.' ('.$p->user?->name.')')->implode("\n"));
        }
        foreach ($late as $key => $group) {
            $mail->line('**'.$group['title'].'**')->line($group['rows']->take(15)->map(fn ($row) => '- '.DeskRows::line($key, $row)['label'])->implode("\n").($group['rows']->count() > 15 ? "\n- and ".($group['rows']->count() - 15).' more' : ''));
        }

        return $mail->action('Open Needs attention', url('/admin/needs-attention'));
    }
}
