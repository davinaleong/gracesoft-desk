<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class FinanceReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $query = Transaction::query()
            ->with(['category', 'account', 'project'])
            ->whereBetween('transaction_date', [$fromDate, $toDate]);

        $transactions = $query->clone()->latest('transaction_date')->get();

        $income = (float) $query->clone()->where('status', 'completed')->where('direction', 'in')->sum('net_amount');
        $expense = (float) $query->clone()->where('status', 'completed')->where('direction', 'out')->sum('net_amount');
        $pending = (float) $query->clone()->where('status', 'pending')->sum('net_amount');

        $expenseByCategory = $query->clone()
            ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.direction', 'out')
            ->selectRaw("COALESCE(transaction_categories.name, 'Uncategorized') as category_name")
            ->selectRaw('COALESCE(SUM(transactions.net_amount), 0) as total_amount')
            ->groupBy('category_name')
            ->orderByDesc('total_amount')
            ->get();

        $incomeByCategory = $query->clone()
            ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.direction', 'in')
            ->selectRaw("COALESCE(transaction_categories.name, 'Uncategorized') as category_name")
            ->selectRaw('COALESCE(SUM(transactions.net_amount), 0) as total_amount')
            ->groupBy('category_name')
            ->orderByDesc('total_amount')
            ->get();

        return [
            'range' => [
                'from' => Carbon::parse($fromDate)->toDateString(),
                'to' => Carbon::parse($toDate)->toDateString(),
            ],
            'totals' => [
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'pending' => round($pending, 2),
                'net' => round($income - $expense, 2),
            ],
            'expense_by_category' => $expenseByCategory,
            'income_by_category' => $incomeByCategory,
            'transactions' => $transactions,
        ];
    }
}
