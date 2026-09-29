<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * SaaS and service spend: completed transactions linked to a service, by vendor and by service category.
 */
class SpendReportService
{
    /**
     * @return array{
     *     range: array{from: string, to: string},
     *     total: float,
     *     by_vendor: array<int, array{vendor: string, total: float, count: int}>,
     *     by_category: array<int, array{category: string, total: float, count: int}>
     * }
     */
    public function build(string $fromDate, string $toDate): array
    {
        $base = Transaction::query()
            ->where('transactions.status', 'completed')
            ->whereNull('transactions.deleted_at')
            ->whereNotNull('transactions.service_id')
            ->whereDate('transactions.transaction_date', '>=', $fromDate)
            ->whereDate('transactions.transaction_date', '<=', $toDate)
            ->join('services', 'services.id', '=', 'transactions.service_id');

        $byVendor = $base->clone()
            ->join('vendors', 'vendors.id', '=', 'services.vendor_id')
            ->selectRaw('vendors.name as vendor, COALESCE(SUM(transactions.amount), 0) as total, COUNT(*) as count')
            ->groupBy('vendors.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['vendor' => (string) $row->vendor, 'total' => round((float) $row->total, 2), 'count' => (int) $row->count])
            ->all();

        $byCategory = $base->clone()
            ->leftJoin('categories', 'categories.id', '=', 'services.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as category, COALESCE(SUM(transactions.amount), 0) as total, COUNT(*) as count")
            ->groupBy(DB::raw("COALESCE(categories.name, 'Uncategorised')"))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['category' => (string) $row->category, 'total' => round((float) $row->total, 2), 'count' => (int) $row->count])
            ->all();

        return [
            'range' => ['from' => $fromDate, 'to' => $toDate],
            'total' => round((float) $base->clone()->sum('transactions.amount'), 2),
            'by_vendor' => $byVendor,
            'by_category' => $byCategory,
        ];
    }
}
