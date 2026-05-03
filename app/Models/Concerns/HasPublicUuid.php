<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @mixin Model */
trait HasPublicUuid
{
    protected static function fillUuid(Model $model): void
    {
        if (empty($model->uuid)) {
            $model->uuid = (string) Str::uuid();
        }
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
