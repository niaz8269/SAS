<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffEnquiryAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public Enquiry $enquiry)
    {
        $this->onQueue('mail');
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New enquiry '.$this->enquiry->reference.' ('.$this->enquiry->category.')');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.staff-enquiry-alert');
    }
}
