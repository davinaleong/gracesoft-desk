@php
    /** @var \App\Models\Invoice $invoice */
    /** @var \App\Services\InvoiceSettings $settings */
    $isTaxInvoice = $invoice->isGstRegistered();
    $fontDir = resource_path('fonts');
    $hasMontserrat = is_file($fontDir.'/Montserrat-Regular.ttf');
    $money = fn ($value) => number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->displayNumber() }}</title>
    <style>
        @if ($hasMontserrat)
            @font-face { font-family: 'Montserrat'; font-weight: normal; src: url('{{ $fontDir }}/Montserrat-Regular.ttf') format('truetype'); }
            @if (is_file($fontDir.'/Montserrat-Bold.ttf'))
                @font-face { font-family: 'Montserrat'; font-weight: bold; src: url('{{ $fontDir }}/Montserrat-Bold.ttf') format('truetype'); }
            @endif
        @endif
        body { font-family: {{ $hasMontserrat ? "'Montserrat', " : '' }}Helvetica, Arial, sans-serif; font-size: 10pt; color: #1f2937; }
        h1 { font-size: 20pt; color: #4338ca; margin: 0 0 4pt; letter-spacing: 1pt; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .header, .parties { width: 100%; margin-bottom: 16pt; }
        .header td, .parties td { vertical-align: top; }
        .right { text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8pt; }
        table.lines th { background: #eef2ff; color: #3730a3; text-align: left; padding: 6pt; font-size: 9pt; }
        table.lines td { padding: 6pt; border-bottom: 1px solid #e5e7eb; }
        table.lines .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; margin-top: 12pt; border-collapse: collapse; }
        table.totals td { padding: 4pt 6pt; }
        table.totals tr.grand td { font-weight: bold; border-top: 2px solid #4338ca; font-size: 11pt; }
        .footer { margin-top: 24pt; font-size: 9pt; color: #6b7280; white-space: pre-line; }
        .stamp { color: #b91c1c; font-weight: bold; font-size: 14pt; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <strong>{{ $settings->companyName() }}</strong><br>
                @if ($settings->companyAddress())
                    <span style="white-space: pre-line">{{ $settings->companyAddress() }}</span><br>
                @endif
                @if ($settings->companyEmail())
                    {{ $settings->companyEmail() }}<br>
                @endif
                @if ($isTaxInvoice)
                    GST Reg. No.: {{ $invoice->gst_registration_number }}
                @endif
            </td>
            <td class="right">
                <h1>{{ $isTaxInvoice ? 'Tax Invoice' : 'Invoice' }}</h1>
                <div>Invoice No.: <strong>{{ $invoice->displayNumber() }}</strong></div>
                <div>Date of Issue: {{ $invoice->issue_date?->format('d M Y') ?? '—' }}</div>
                <div>Due Date: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
                @if ($invoice->status === \App\Models\Invoice::STATUS_VOID)
                    <div class="stamp">VOID</div>
                @elseif ($invoice->status === \App\Models\Invoice::STATUS_PAID)
                    <div class="stamp" style="color:#15803d">PAID</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="muted">Bill To</div>
                <strong>{{ $invoice->client->name }}</strong><br>
                @if ($invoice->client->address)
                    <span style="white-space: pre-line">{{ $invoice->client->address }}</span><br>
                @endif
                @if ($invoice->client->tax_id)
                    Reg. / Tax ID: {{ $invoice->client->tax_id }}
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount{{ $isTaxInvoice ? ' (excl. GST)' : '' }}</th>
                @if ($isTaxInvoice)
                    <th class="num">GST</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ $line->quantity }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->amount) }}</td>
                    @if ($isTaxInvoice)
                        <td class="num">{{ $money($line->gst_amount) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($isTaxInvoice)
            <tr><td>Total excl. GST</td><td class="right">{{ $invoice->currency }} {{ $money($invoice->subtotal) }}</td></tr>
            <tr><td>GST @ {{ rtrim(rtrim(number_format((float) $invoice->gst_rate, 2), '0'), '.') }}%</td><td class="right">{{ $invoice->currency }} {{ $money($invoice->gst_amount) }}</td></tr>
            <tr class="grand"><td>Total incl. GST</td><td class="right">{{ $invoice->currency }} {{ $money($invoice->total) }}</td></tr>
        @else
            <tr class="grand"><td>Total</td><td class="right">{{ $invoice->currency }} {{ $money($invoice->total) }}</td></tr>
        @endif
    </table>

    @if ($invoice->notes)
        <p style="white-space: pre-line; margin-top: 16pt">{{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Payment terms: {{ $invoice->payment_terms_days }} days.
        @if ($invoice->footer)
            {{ "\n".$invoice->footer }}
        @endif
    </div>
</body>
</html>
