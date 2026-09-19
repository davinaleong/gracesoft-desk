<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'vendor_code',
        'name',
        'category_id',
        'website',
        'support_url',
        'account_number',
        'status',
        'notes',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $vendor): void {
            self::fillUuid($vendor);

            if (empty($vendor->vendor_code)) {
                $vendor->vendor_code = self::generateVendorCode();
            }
        });
    }

    protected static function generateVendorCode(): string
    {
        $latest = self::query()
            ->withTrashed()
            ->where('vendor_code', 'like', 'VND-%')
            ->orderByDesc('id')
            ->value('vendor_code');

        $next = $latest
            ? (int) substr($latest, 4) + 1
            : 1;

        return sprintf('VND-%05d', $next);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('status', 'inactive');
    }
}
