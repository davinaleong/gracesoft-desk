<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $paymentMethod): void {
            self::fillUuid($paymentMethod);
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'meta',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
