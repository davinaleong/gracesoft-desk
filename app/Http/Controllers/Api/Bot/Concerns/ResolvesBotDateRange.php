<?php

namespace App\Http\Controllers\Api\Bot\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ResolvesBotDateRange
{
    /**
     * Resolves the `from`/`to` date range for a bot summary endpoint:
     * - `?from=YYYY-MM&to=YYYY-MM` — from the start of `from` to the end of `to`
     * - `?month=YYYY-MM` — a single calendar month
     * - neither — a rolling 30-day window
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $fromMonth = $request->string('from')->toString();
        $toMonth = $request->string('to')->toString();

        if ($fromMonth !== '' || $toMonth !== '') {
            return $this->resolveMonthRange($fromMonth, $toMonth);
        }

        $month = $request->string('month')->toString();

        if ($month === '') {
            return [
                now()->subDays(30)->startOfDay()->toDateString(),
                now()->endOfDay()->toDateString(),
            ];
        }

        $start = $this->parseBotMonth($month, 'month');

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveMonthRange(string $fromMonth, string $toMonth): array
    {
        $start = $this->parseBotMonth($fromMonth, 'from');
        $end = $this->parseBotMonth($toMonth, 'to');

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'to' => 'The "to" month must not be before the "from" month.',
            ]);
        }

        return [$start->toDateString(), $end->copy()->endOfMonth()->toDateString()];
    }

    private function parseBotMonth(string $month, string $field): Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (Throwable) {
            $parsed = null;
        }

        if ($parsed === null || $parsed->format('Y-m') !== $month) {
            throw ValidationException::withMessages([
                $field => "The {$field} must be in the YYYY-MM format.",
            ]);
        }

        return $parsed;
    }
}
