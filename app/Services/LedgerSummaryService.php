<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class LedgerSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $transactions = Transaction::query()
            ->whereBetween('transaction_date', [$fromDate, $toDate])
            ->get();

        $monthlySummary = $transactions
            ->groupBy(fn (Transaction $transaction): string => (string) $transaction->transaction_date?->format('Y-m'))
            ->sortKeys()
            ->map(function (Collection $rows, string $monthKey): array {
                $monthLabel = $rows->first()?->transaction_date?->format('M Y') ?? $monthKey;

                return [
                    'month_key' => $monthKey,
                    'month_label' => $monthLabel,
                    'income_total' => round((float) $rows
                        ->where('status', 'completed')
                        ->where('direction', 'in')
                        ->sum('net_amount'), 2),
                    'expense_total' => round((float) $rows
                        ->where('status', 'completed')
                        ->where('direction', 'out')
                        ->sum('net_amount'), 2),
                    'pending_total' => round((float) $rows
                        ->where('status', 'pending')
                        ->sum('net_amount'), 2),
                ];
            })
            ->values();

        $accountSummary = Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->whereBetween('transactions.transaction_date', [$fromDate, $toDate])
            ->select('accounts.code', 'accounts.name')
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.direction = 'in' AND transactions.status = 'completed' THEN transactions.net_amount ELSE 0 END), 0) as income_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.direction = 'out' AND transactions.status = 'completed' THEN transactions.net_amount ELSE 0 END), 0) as expense_total")
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name')
            ->orderByDesc('income_total')
            ->get();

        return [
            'range' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'monthly_summary' => $monthlySummary,
            'account_summary' => $accountSummary,
        ];
    }
}
