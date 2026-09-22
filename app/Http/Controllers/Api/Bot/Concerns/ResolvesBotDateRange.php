<?php

namespace App\Http\Controllers\Api\Bot\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ResolvesBotDateRange
{
    /**
     * Resolves the `from`/`to` date range for a bot summary endpoint: a
     * rolling 30-day window by default, or a full calendar month when
     * `?month=YYYY-MM` is given.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $month = $request->string('month')->toString();

        if ($month === '') {
            return [
                now()->subDays(30)->startOfDay()->toDateString(),
                now()->endOfDay()->toDateString(),
            ];
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (Throwable) {
            $start = null;
        }

        if ($start === null || $start->format('Y-m') !== $month) {
            throw ValidationException::withMessages([
                'month' => 'The month must be in the YYYY-MM format.',
            ]);
        }

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }
}
