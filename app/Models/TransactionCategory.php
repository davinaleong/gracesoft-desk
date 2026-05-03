<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            self::fillUuid($category);
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_active',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
