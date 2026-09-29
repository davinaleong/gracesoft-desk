<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * Receivables totals shared by the finance report, its CSV export and the bot API.
 */
class InvoiceTotals
{
    /**
     * - invoiced: invoices issued in the range (still issued, or since paid)
     * - paid: invoices marked paid in the range
     * - outstanding: every issued, unpaid invoice right now
     *
     * @return array{invoiced: float, paid: float, outstanding: float}
     */
    public function forRange(string $fromDate, string $toDate): array
    {
        $invoiced = (float) Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->whereDate('issue_date', '>=', $fromDate)
            ->whereDate('issue_date', '<=', $toDate)
            ->sum('total');

        $paid = (float) Invoice::query()
            ->where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->sum('total');

        $outstanding = (float) Invoice::query()->outstanding()->sum('total');

        return [
            'invoiced' => round($invoiced, 2),
            'paid' => round($paid, 2),
            'outstanding' => round($outstanding, 2),
        ];
    }
}
