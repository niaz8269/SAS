<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** The counsellor's morning list by email: the same five groups as Today on the dashboard. */
class CounsellorDigest extends Notification
{
    /** @param array{replies:Collection,due:Collection,stale:Collection,deadlines:Collection,returned:Collection,total:int} $work */
    public function __construct(public array $work) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(config('sas.brand.short').': '.$this->work['total'].' '.str('thing')->plural($this->work['total']).' for your students today')->greeting('Good morning '.$notifiable->name);
        $line = fn (string $title, $rows, callable $text) => $rows->isEmpty() ? null : $mail->line('**'.$title.'**')->line($rows->map($text)->implode("\n"));
        $line('Waiting for your reply', $this->work['replies'], fn ($r) => '- '.$r['student']->name.' (wrote '.$r['since']->diffForHumans().')');
        $line('Follow-ups due', $this->work['due'], fn ($r) => '- '.$r['student']->name.' ('.$r['date']->format('j M').')');
        $line('Gone quiet', $this->work['stale'], fn ($r) => '- '.$r['student']->name.($r['never'] ? ' (never updated)' : ' ('.$r['days'].' days)'));
        $line('Deadlines soon', $this->work['deadlines'], fn ($r) => '- '.$r['student']->name.': '.$r['plan']->cycle->programme->title.' closes '.$r['closes']->format('j M'));
        $line('Sent back by SAS', $this->work['returned'], fn ($r) => '- '.$r['student']->name.' ('.$r['plan']->application_no.')');

        return $mail->action('Open Today', url('/admin'));
    }
}
