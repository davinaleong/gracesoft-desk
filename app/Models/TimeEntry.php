<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            self::deriveBillingValues($entry);
        });

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

    public function scopeWithinDateRange(Builder $query, string $fromDate, string $toDate): Builder
    {
        return $query->whereBetween('entry_date', [$fromDate, $toDate]);
    }

    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('is_billable', true);
    }

    private static function deriveBillingValues(self $entry): void
    {
        $durationMinutes = max(0, (int) ($entry->duration_minutes ?? 0));
        $hourlyRate = max(0, (float) ($entry->hourly_rate ?? 0));
        $isBillable = (bool) ($entry->is_billable ?? false);

        $entry->duration_minutes = $durationMinutes;
        $entry->hourly_rate = $hourlyRate;

        if (! $isBillable || $durationMinutes === 0 || $hourlyRate === 0.0) {
            $entry->billable_amount = 0;

            return;
        }

        $entry->billable_amount = round(($durationMinutes / 60) * $hourlyRate, 2);
    }
}
