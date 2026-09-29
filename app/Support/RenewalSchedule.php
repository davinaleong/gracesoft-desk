<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Renewal date arithmetic that remembers the day a subscription started on.
 *
 * A monthly service renewing on the 31st moves to 28/29 February and then back to the 31st;
 * a yearly renewal on 29 February lands on 28 February in non-leap years and returns to the 29th.
 */
class RenewalSchedule
{
    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const CYCLES = [self::MONTHLY, self::YEARLY];

    public static function next(CarbonInterface $current, string $cycle, ?int $anchorDay = null): CarbonImmutable
    {
        $current = CarbonImmutable::instance($current)->startOfDay();
        $anchorDay ??= $current->day;

        $firstOfTarget = $cycle === self::YEARLY
            ? $current->startOfMonth()->addYearNoOverflow()
            : $current->startOfMonth()->addMonthNoOverflow();

        return $firstOfTarget->setDay(min($anchorDay, $firstOfTarget->daysInMonth));
    }
}
