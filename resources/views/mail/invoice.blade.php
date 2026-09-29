<x-mail::message>
# {{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}

{{ __('Hello :name,', ['name' => $invoice->client->name]) }}

{{ __('Please find attached invoice :number for :amount, due on :date.', [
    'number' => $invoice->invoice_number,
    'amount' => $invoice->currency.' '.number_format((float) $invoice->total, 2),
    'date' => $invoice->due_date?->format('d M Y'),
]) }}

{{ __('Thank you,') }}<br>
{{ $companyName }}
</x-mail::message>
