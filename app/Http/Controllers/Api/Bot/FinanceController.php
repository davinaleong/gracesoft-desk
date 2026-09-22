<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Api\Bot\Concerns\ResolvesBotDateRange;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ResolvesBotDateRange;

    public function summary(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $query = Transaction::query()->withinDateRange($fromDate, $toDate)->completed();

        $income = (float) $query->clone()->direction('in')->sum('net_amount');
        $expense = (float) $query->clone()->direction('out')->sum('net_amount');
        $cashPosition = (float) Account::query()->where('is_active', true)->sum('current_balance');

        return response()->json([
            'range' => ['from' => $fromDate, 'to' => $toDate],
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'cash_position' => round($cashPosition, 2),
        ]);
    }
}
