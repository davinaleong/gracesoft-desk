<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Api\Bot\Concerns\ResolvesBotDateRange;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicesController extends Controller
{
    use ResolvesBotDateRange;

    public function summary(Request $request, InvoiceTotals $totals): JsonResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $range = $totals->forRange($fromDate, $toDate);

        $overdue = Invoice::query()
            ->outstanding()
            ->whereDate('due_date', '<', now()->toDateString())
            ->with('client')
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'range' => ['from' => $fromDate, 'to' => $toDate],
            'invoiced' => $range['invoiced'],
            'paid' => $range['paid'],
            'outstanding' => $range['outstanding'],
            'outstanding_count' => Invoice::query()->outstanding()->count(),
            'overdue' => round((float) $overdue->sum('total'), 2),
            'overdue_invoices' => $overdue->map(fn (Invoice $invoice): array => [
                'number' => $invoice->invoice_number,
                'client' => $invoice->client?->name,
                'total' => round((float) $invoice->total, 2),
                'due_date' => $invoice->due_date?->toDateString(),
                'days_overdue' => (int) $invoice->due_date?->diffInDays(now()->startOfDay()),
            ])->values(),
        ]);
    }
}
