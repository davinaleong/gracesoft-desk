<?php

namespace App\Mail;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Service $service) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':service renews on :date', [
                'service' => $this->service->name,
                'date' => $this->service->next_renewal_date?->format('d M Y'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.renewal-reminder');
    }
}
