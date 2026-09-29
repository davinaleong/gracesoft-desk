<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budget threshold a project crossed. One row per project, budget revision and threshold,
 * so each threshold fires once until the budget itself changes.
 */
class BudgetAlert extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'project_id',
        'budget_revision',
        'threshold',
        'budget_type',
        'budget_value',
        'used_value',
        'triggered_at',
        'notified_at',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $alert): void {
            self::fillUuid($alert);
        });
    }

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'budget_revision' => 'integer',
            'budget_value' => 'decimal:2',
            'used_value' => 'decimal:2',
            'triggered_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
