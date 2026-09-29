<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingCommitsReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public int $pendingCount) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans_choice('{1} :count commit is waiting for your timesheet|[2,*] :count commits are waiting for your timesheet', $this->pendingCount),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pending-commits-reminder');
    }
}
