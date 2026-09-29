<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    use HasPublicUuid;

    public const TYPE_TIME = 'time';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_MILESTONE = 'milestone';

    public const TYPE_RETAINER = 'retainer';

    protected $fillable = [
        'invoice_id',
        'project_id',
        'project_stage_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'gst_amount',
        'sort_order',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            self::fillUuid($line);
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
