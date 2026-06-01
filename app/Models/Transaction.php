<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Transaction extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'transaction_code',
        'account_id',
        'transaction_category_id',
        'payment_method_id',
        'project_id',
        'type',
        'direction',
        'status',
        'transaction_date',
        'reference',
        'description',
        'amount',
        'gst_amount',
        'net_amount',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            self::enforceIntegrity($transaction);
        });

        static::creating(function (self $transaction): void {
            self::fillUuid($transaction);

            if (empty($transaction->transaction_code)) {
                $transaction->transaction_code = self::generateTransactionCode();
            }
        });
    }

    protected static function generateTransactionCode(): string
    {
        do {
            $candidate = sprintf(
                'TRX-%s-%s',
                now()->format('Ymd'),
                strtoupper(Str::random(6))
            );
        } while (self::query()->where('transaction_code', $candidate)->exists());

        return $candidate;
    }

    private static function enforceIntegrity(self $transaction): void
    {
        $type = strtolower((string) $transaction->type);
        $direction = strtolower((string) $transaction->direction);
        $amount = (float) $transaction->amount;
        $gstAmount = (float) $transaction->gst_amount;

        if ($gstAmount > $amount) {
            throw ValidationException::withMessages([
                'gst_amount' => 'GST cannot be higher than the transaction amount.',
            ]);
        }

        if ($type === 'income' && $direction !== 'in') {
            throw ValidationException::withMessages([
                'direction' => 'For income transactions, please choose the "Money In" direction.',
            ]);
        }

        if ($type === 'expense' && $direction !== 'out') {
            throw ValidationException::withMessages([
                'direction' => 'For expense transactions, please choose the "Money Out" direction.',
            ]);
        }

        $transaction->net_amount = max(0, round($amount - $gstAmount, 2));
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeWithinDateRange(Builder $query, string $fromDate, string $toDate): Builder
    {
        return $query->whereBetween('transaction_date', [$fromDate, $toDate]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeDirection(Builder $query, string $direction): Builder
    {
        return $query->where('direction', $direction);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
