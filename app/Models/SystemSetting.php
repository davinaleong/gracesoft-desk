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
            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_null($value) ? null : (string) $value],
            );
        }
    }
}
