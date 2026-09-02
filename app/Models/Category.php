<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            self::fillUuid($category);
        });
    }

    protected $fillable = [
        'type',
        'name',
        'status',
    ];

    protected $hidden = ['id'];

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
