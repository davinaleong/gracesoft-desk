<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_billable' => 'boolean',
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
}
