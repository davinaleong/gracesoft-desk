<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'category',
        'status',
        'notes',
    ];

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

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeByVendor(Builder $query, int $vendorId): void
    {
        $query->where('vendor_id', $vendorId);
    }

    public function scopeByCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
}
