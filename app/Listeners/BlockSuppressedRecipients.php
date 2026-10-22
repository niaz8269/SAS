<?php

namespace App\Listeners;

use App\Domain\Communication\EmailSuppressions;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/** Backstop for every outgoing email: if all recipients are suppressed the message is not sent. */
class BlockSuppressedRecipients
{
    public function __construct(private EmailSuppressions $suppressions) {}

    public function handle(MessageSending $event): ?bool
    {
        $to = collect($event->message->getTo())->map(fn ($a) => $a->getAddress());
        if ($to->isEmpty()) {
            return null;
        }
        $blocked = $to->filter(fn ($a) => $this->suppressions->isSuppressed($a));
        if ($blocked->count() === $to->count()) {
            Log::info('Email not sent: all recipients are suppressed.', ['recipients' => $blocked->count()]); // no addresses in logs

            return false;
        }

        return null;
    }
}
