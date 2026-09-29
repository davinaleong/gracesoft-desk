<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an issued invoice to PDF and stores it on S3 as a Document linked to the invoice.
 */
class InvoicePdfService
{
    public function __construct(private InvoiceSettings $settings) {}

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['client', 'lines']);

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $this->settings,
        ])
            ->setOption(['isFontSubsettingEnabled' => true, 'defaultFont' => 'helvetica'])
            ->setPaper('a4')
            ->output();
    }

    /**
     * Returns the stored PDF document, generating it first if it is missing.
     */
    public function ensureStored(Invoice $invoice): Document
    {
        $existing = $invoice->pdfDocument;

        if ($existing !== null && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        return $this->store($invoice);
    }

    public function store(Invoice $invoice): Document
    {
        $contents = $this->render($invoice);
        $fileName = $invoice->displayNumber().'.pdf';
        $path = 'invoices/'.$invoice->uuid.'/'.$fileName;

        Storage::disk('s3')->put($path, $contents);

        return DB::transaction(function () use ($invoice, $path, $fileName, $contents): Document {
            $previous = $invoice->pdfDocument;

            $document = Document::query()->create([
                'name' => $fileName,
                'disk' => 's3',
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => strlen($contents),
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'user_id' => auth()->id(),
            ]);

            $invoice->forceFill(['document_id' => $document->id])->saveQuietly();
            $invoice->setRelation('pdfDocument', $document);

            if ($previous !== null && $previous->path !== $path) {
                $previous->delete();
            } elseif ($previous !== null) {
                // Same path was just overwritten; drop the old row without deleting the file.
                $previous->deleteQuietly();
            }

            return $document;
        });
    }
}
