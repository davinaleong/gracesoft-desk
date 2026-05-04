<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getMappedValues(): Collection
    {
        return self::query()->pluck('value', 'key');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function upsertValues(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if (is_bool($value)) {
                $normalizedValue = $value ? '1' : '0';
            } elseif (is_null($value)) {
                $normalizedValue = null;
            } else {
                $normalizedValue = (string) $value;
            }

            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $normalizedValue],
            );
        }
    }
}
