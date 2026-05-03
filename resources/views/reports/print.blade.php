<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
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
                @foreach ($report['transactions'] as $transaction)
                    <tr>
                        <td>{{ $transaction->transaction_code }}</td>
                        <td>{{ $transaction->transaction_date?->toDateString() }}</td>
                        <td>{{ $transaction->type }} / {{ $transaction->direction }}</td>
                        <td>{{ $transaction->status }}</td>
                        <td>{{ number_format((float) $transaction->net_amount, 2) }}</td>
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
                        <td>{{ $row->code }} - {{ $row->name }}</td>
                        <td>{{ number_format(((int) $row->duration_minutes) / 60, 2) }}</td>
                        <td>{{ number_format((float) $row->billable_amount, 2) }}</td>
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
                        <td>{{ number_format((float) $row['income_total'], 2) }}</td>
                        <td>{{ number_format((float) $row['expense_total'], 2) }}</td>
                        <td>{{ number_format((float) $row['pending_total'], 2) }}</td>
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
