<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if ($reportType === 'finance')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap"
            rel="stylesheet">
    @endif
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 24px;
        }

        h1 {
            margin-bottom: 8px;
        }

        p.meta {
            color: #6b7280;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }

        th {
            background: #f3f4f6;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        @if ($reportType === 'finance')
            body {
                font-family: 'Montserrat', Arial, sans-serif;
            }
        @endif
    </style>
</head>

<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ __('From') }}: {{ $report['range']['from'] }} | {{ __('To') }}:
        {{ $report['range']['to'] }}</p>

    @if ($reportType === 'finance')
        <table>
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Net Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $transactions = collect($report['transactions'] ?? [])->filter(
                        fn($row) => is_array($row) || is_object($row),
                    );
                @endphp

                @foreach ($transactions as $transaction)
                    <tr>
                        <td><span
                                style="font-family: monospace;">{{ data_get($transaction, 'transaction_code', '-') }}</span>
                        </td>
                        <td>@deskDate((string) data_get($transaction, 'transaction_date', now()->toDateString()))</td>
                        <td>{{ data_get($transaction, 'type', '-') }} / {{ data_get($transaction, 'direction', '-') }}
                        </td>
                        <td>{{ data_get($transaction, 'status', '-') }}</td>
                        <td>@deskMoney((float) data_get($transaction, 'net_amount', 0))</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($reportType === 'projects')
        <table>
            <thead>
                <tr>
                    <th>{{ __('Project') }}</th>
                    <th>{{ __('Hours') }}</th>
                    <th>{{ __('Billable') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['project_summary'] as $row)
                    <tr>
                        <td><span style="font-family: monospace;">{{ $row->code }}</span> - {{ $row->name }}
                        </td>
                        <td>@deskDuration((int) $row->duration_minutes)</td>
                        <td>@deskMoney((float) $row->billable_amount)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($reportType === 'monthly')
        <table>
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th>{{ __('Income') }}</th>
                    <th>{{ __('Expense') }}</th>
                    <th>{{ __('Pending') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['monthly_summary'] as $row)
                    <tr>
                        <td>{{ $row['month_label'] }}</td>
                        <td>@deskMoney((float) $row['income_total'])</td>
                        <td>@deskMoney((float) $row['expense_total'])</td>
                        <td>@deskMoney((float) $row['pending_total'])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <script>
        window.print();
    </script>
</body>

</html>
