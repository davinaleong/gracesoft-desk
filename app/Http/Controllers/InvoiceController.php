<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordInvoicePaymentRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Requests\VoidInvoiceRequest;
use App\Mail\InvoiceMail;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\TransactionCategory;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoices,
        private InvoicePdfService $pdfs,
    ) {}

    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with('client')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'outstandingTotal' => (float) Invoice::query()->outstanding()->sum('total'),
        ]);
    }

    public function create(Request $request): View
    {
        $client = $request->filled('client')
            ? Client::query()->where('uuid', $request->string('client')->toString())->first()
            : null;

        return view('invoices.create', [
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'client' => $client,
            'entries' => $client ? $this->invoices->unbilledEntriesFor($client)->get() : collect(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $client = Client::query()->where('uuid', $validated['client_uuid'])->firstOrFail();

        $invoice = $this->invoices->createDraft(
            client: $client,
            timeEntryUuids: $validated['time_entry_uuids'] ?? [],
            grouping: $validated['grouping'],
            manualLines: $validated['manual_lines'] ?? [],
            notes: $validated['notes'] ?? null,
            user: $request->user(),
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-created');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['client', 'lines.project', 'paymentTransaction', 'pdfDocument']);

        return view('invoices.show', [
            'invoice' => $invoice,
            'accounts' => Account::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::query()->orderBy('name')->get(),
            'categories' => TransactionCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (! $invoice->isEditable()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('Issued invoices can\'t be edited.'));
        }

        $invoice->load(['client', 'lines']);

        return view('invoices.edit', [
            'invoice' => $invoice,
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validated();

        $this->invoices->updateDraft(
            invoice: $invoice,
            manualLines: $validated['manual_lines'] ?? [],
            removeLineUuids: $validated['remove_line_uuids'] ?? [],
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-updated');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->invoices->deleteDraft($invoice);

        return redirect()
            ->route('invoices.index')
            ->with('status', 'invoice-deleted');
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        $invoice = $this->invoices->issue($invoice);
        $this->refreshPdf($invoice);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-issued');
    }

    public function pdf(Invoice $invoice): RedirectResponse
    {
        if ($invoice->isDraft()) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('Issue the invoice before downloading its PDF.'));
        }

        return redirect()->away($this->pdfs->ensureStored($invoice)->temporaryUrl());
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID], true)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('Only issued invoices can be sent.'));
        }

        $invoice->loadMissing('client');

        if (blank($invoice->client->billing_email)) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', __('Add a billing email to the client before sending.'));
        }

        $pdf = $this->pdfs->ensureStored($invoice);

        Mail::to($invoice->client->billing_email)->send(new InvoiceMail($invoice, $pdf));

        $invoice->update(['sent_at' => now()]);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-sent');
    }

    public function recordPayment(RecordInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->recordPayment($invoice, $request->validated());
        $this->refreshPdf($invoice->fresh());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-paid');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice = $this->invoices->void($invoice, $request->string('void_reason')->toString());

        if ($invoice->invoice_number !== null) {
            $this->refreshPdf($invoice);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'invoice-voided');
    }

    /**
     * Regenerate the stored PDF so it matches the invoice's status. A failure never undoes the action.
     */
    private function refreshPdf(Invoice $invoice): void
    {
        try {
            $this->pdfs->store($invoice);
        } catch (Throwable $exception) {
            Log::warning('Invoice PDF generation failed', [
                'invoice' => $invoice->uuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
