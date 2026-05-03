<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DeskFormat
{
    public static function money(float|int|string|null $amount, string $currency = 'SGD'): string
    {
        $value = (float) ($amount ?? 0);

        return $currency.' '.number_format($value, 2);
    }

    public static function date(mixed $value, string $fallback = 'N/A'): string
    {
        if (is_null($value) || $value === '') {
            return $fallback;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function durationMinutes(int|float|string|null $minutes): string
    {
        $durationMinutes = max(0, (int) ($minutes ?? 0));

        $hours = intdiv($durationMinutes, 60);
        $remainingMinutes = $durationMinutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return sprintf('%dh %dm', $hours, $remainingMinutes);
        }

        if ($hours > 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dm', $remainingMinutes);
    }
}
