<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    public const STATUSES = ['active', 'archived'];

    protected $fillable = [
        'client_code',
        'name',
        'billing_email',
        'address',
        'tax_id',
        'currency',
        'default_hourly_rate',
        'status',
        'notes',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $client): void {
            self::fillUuid($client);

            if (empty($client->client_code)) {
                $client->client_code = self::generateClientCode();
            }
        });
    }

    protected static function generateClientCode(): string
    {
        $latest = self::query()
            ->withTrashed()
            ->where('client_code', 'like', 'CLT-%')
            ->orderByDesc('id')
            ->value('client_code');

        $next = $latest
            ? (int) substr($latest, 4) + 1
            : 1;

        return sprintf('CLT-%05d', $next);
    }

    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
