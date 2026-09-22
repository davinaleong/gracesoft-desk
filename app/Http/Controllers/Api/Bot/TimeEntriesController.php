<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Api\Bot\Concerns\ResolvesBotDateRange;
use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntriesController extends Controller
{
    use ResolvesBotDateRange;

    public function summary(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $entries = TimeEntry::query()->withinDateRange($fromDate, $toDate);

        $billableMinutes = (int) $entries->clone()->billable()->sum('duration_minutes');
        $totalMinutes = (int) $entries->clone()->sum('duration_minutes');
        $nonBillableMinutes = max(0, $totalMinutes - $billableMinutes);
        $billableAmount = (float) $entries->clone()->billable()->sum('billable_amount');

        return response()->json([
            'range' => ['from' => $fromDate, 'to' => $toDate],
            'billable_hours' => round($billableMinutes / 60, 2),
            'non_billable_hours' => round($nonBillableMinutes / 60, 2),
            'total_hours' => round($totalMinutes / 60, 2),
            'billable_amount' => round($billableAmount, 2),
        ]);
    }
}
