<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_PAID, self::STATUS_VOID];

    protected $fillable = [
        'client_id',
        'invoice_number',
        'status',
        'currency',
        'issue_date',
        'due_date',
        'payment_terms_days',
        'gst_rate',
        'gst_registration_number',
        'subtotal',
        'gst_amount',
        'total',
        'notes',
        'footer',
        'document_id',
        'issued_at',
        'sent_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            self::fillUuid($invoice);
        });
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'payment_terms_days' => 'integer',
            'gst_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pdfDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function paymentTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    public function isGstRegistered(): bool
    {
        return filled($this->gst_registration_number);
    }

    public function displayNumber(): string
    {
        return $this->invoice_number ?? __('Draft');
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->where('status', self::STATUS_ISSUED);
    }
}
