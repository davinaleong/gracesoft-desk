<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Stores a timestamp in UTC whatever the app timezone is, and hands it back in the app timezone.
 *
 * The plain `datetime` cast writes the wall-clock time and drops the offset, so a push
 * timestamped 23:30+08:00 would be stored as 23:30 and read back in a different zone.
 *
 * @implements CastsAttributes<Carbon, CarbonInterface|string>
 */
class UtcDateTime implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', (string) $value, 'UTC')
            ->setTimezone(config('app.timezone'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $moment = $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, config('app.timezone'));

        return $moment->utc()->format('Y-m-d H:i:s');
    }
}
