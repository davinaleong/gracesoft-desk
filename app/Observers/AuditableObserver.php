<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->storeLog($model, 'created', null, $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $keys = array_keys($changes);
        $oldValues = Arr::only($model->getOriginal(), $keys);
        $newValues = Arr::only($model->getAttributes(), $keys);

        $this->storeLog($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->storeLog($model, 'deleted', $this->snapshot($model), null);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $model): array
    {
        return $model->getAttributes();
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function storeLog(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'auditable_type' => $model::class,
            'auditable_id' => (int) $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'occurred_at' => now(),
        ]);
    }
}
