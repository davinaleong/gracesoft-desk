<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            self::fillUuid($entry);
        });
    }

    protected $fillable = [
        'project_id',
        'project_stage_id',
        'user_id',
        'entry_date',
        'duration_minutes',
        'is_billable',
        'hourly_rate',
        'billable_amount',
        'notes',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_billable' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'billable_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
