<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class FinanceReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $cacheKey = sprintf(
            'reports.finance.%s.%s.%s',
            $fromDate,
            $toDate,
            $this->transactionsCacheSignature()
        );

        $callback = function () use ($fromDate, $toDate): array {
            $query = Transaction::query()
                ->withinDateRange($fromDate, $toDate)
                ->with(['category', 'account', 'project']);

            $transactions = $query->clone()->latest('transaction_date')->get();

            $income = (float) $query->clone()->completed()->direction('in')->sum('net_amount');
            $expense = (float) $query->clone()->completed()->direction('out')->sum('net_amount');
            $pending = (float) $query->clone()->pending()->sum('net_amount');

            $expenseByCategory = $query->clone()
                ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
                ->completed()
                ->direction('out')
                ->selectRaw("COALESCE(transaction_categories.name, 'Uncategorized') as category_name")
                ->selectRaw('COALESCE(SUM(transactions.net_amount), 0) as total_amount')
                ->groupBy('category_name')
                ->orderByDesc('total_amount')
                ->get();

            $incomeByCategory = $query->clone()
                ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
                ->completed()
                ->direction('in')
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
        };

        if (app()->environment('testing')) {
            return $callback();
        }

        return Cache::remember($cacheKey, now()->addMinutes(5), $callback);
    }

    private function transactionsCacheSignature(): string
    {
        $count = Transaction::query()->count();
        $latestUpdate = (string) (Transaction::query()->max('updated_at') ?? 'none');

        return sha1($count.'|'.$latestUpdate);
    }
}
