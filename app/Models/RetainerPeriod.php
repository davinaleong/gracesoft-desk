<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One month of a retainer: what was included, carried in, used, charged as overage and carried out.
 * Unique per project and period, so each month's draft is generated once.
 */
class RetainerPeriod extends Model
{
    protected $fillable = [
        'project_id',
        'period',
        'invoice_id',
        'included_hours',
        'rollover_in_hours',
        'hours_used',
        'overage_hours',
        'rollover_out_hours',
    ];

    protected function casts(): array
    {
        return [
            'included_hours' => 'decimal:2',
            'rollover_in_hours' => 'decimal:2',
            'hours_used' => 'decimal:2',
            'overage_hours' => 'decimal:2',
            'rollover_out_hours' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
