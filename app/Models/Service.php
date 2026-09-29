<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'service_code',
        'vendor_id',
        'name',
        'plan',
        'category_id',
        'status',
        'notes',
        'billing_cycle',
        'expected_amount',
        'currency',
        'next_renewal_date',
        'renewal_anchor_day',
        'account_id',
        'payment_method_id',
        'transaction_category_id',
        'auto_create_expense',
        'reminder_days_before',
        'last_reminded_for',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'next_renewal_date' => 'date',
            'last_reminded_for' => 'date',
            'renewal_anchor_day' => 'integer',
            'auto_create_expense' => 'boolean',
            'reminder_days_before' => 'integer',
        ];
    }

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $service): void {
            self::fillUuid($service);

            if (empty($service->service_code)) {
                $service->service_code = self::generateServiceCode();
            }
        });
    }

    protected static function generateServiceCode(): string
    {
        $latest = self::query()
            ->withTrashed()
            ->where('service_code', 'like', 'SVC-%')
            ->orderByDesc('id')
            ->value('service_code');

        $next = $latest
            ? (int) substr($latest, 4) + 1
            : 1;

        return sprintf('SVC-%05d', $next);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Active services with a tracked cycle renewing between today and the given number of days out.
     */
    public function scopeRenewingWithin(Builder $query, int $days): void
    {
        $today = now()->toDateString();

        $query->where('status', 'active')
            ->whereNotNull('billing_cycle')
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '>=', $today)
            ->whereDate('next_renewal_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeByVendor(Builder $query, int $vendorId): void
    {
        $query->where('vendor_id', $vendorId);
    }

    public function scopeByCategory(Builder $query, int $categoryId): void
    {
        $query->where('category_id', $categoryId);
    }
}
