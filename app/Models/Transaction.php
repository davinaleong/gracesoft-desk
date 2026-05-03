<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;
    use HasPublicUuid;

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
}
