<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed-fee project milestone. It becomes an invoice line once, and is released if that invoice is voided.
 */
class Milestone extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'project_id',
        'name',
        'amount',
        'due_date',
        'status',
        'invoice_line_id',
        'sort_order',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $milestone): void {
            self::fillUuid($milestone);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
