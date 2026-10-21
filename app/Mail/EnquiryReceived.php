<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Transactional (NOT-02). Contains a reference and a limited-purpose link, never any document. */
class EnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public Enquiry $enquiry, public string $link)
    {
        $this->onQueue('mail');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We received your enquiry '.$this->enquiry->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.enquiry-received');
    }
}
