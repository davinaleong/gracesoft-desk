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
                        <td><span style="font-family: monospace;">{{ $transaction->transaction_code }}</span></td>
                        <td>@deskDate($transaction->transaction_date)</td>
                        <td>{{ $transaction->type }} / {{ $transaction->direction }}</td>
                        <td>{{ $transaction->status }}</td>
                        <td>@deskMoney((float) $transaction->net_amount)</td>
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
                        <td><span style="font-family: monospace;">{{ $row->code }}</span> - {{ $row->name }}</td>
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
