<?php

namespace App\Mail;

use App\Models\BudgetAlert;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BudgetAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Project $project, public BudgetAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':project has used :threshold% of its budget', [
                'project' => $this->project->code,
                'threshold' => $this->alert->threshold,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.budget-alert');
    }
}
