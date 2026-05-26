<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStage extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $stage): void {
            self::fillUuid($stage);
        });
    }

    protected $fillable = [
        'name',
        'sort_order',
        'status',
    ];

    protected $hidden = ['id'];

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
