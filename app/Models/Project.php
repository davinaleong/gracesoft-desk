<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            self::fillUuid($project);
        });
    }

    protected $fillable = [
        'code',
        'name',
        'status',
        'description',
        'starts_on',
        'ends_on',
        'is_billable',
        'hourly_rate',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_billable' => 'boolean',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
