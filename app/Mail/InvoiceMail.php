<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\Invoice;
use App\Services\InvoiceSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public Document $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invoice :number from :company', [
                'number' => $this->invoice->invoice_number,
                'company' => app(InvoiceSettings::class)->companyName(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'companyName' => app(InvoiceSettings::class)->companyName(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->pdf->disk, $this->pdf->path)
                ->as($this->pdf->name)
                ->withMime('application/pdf'),
        ];
    }
}
