<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\BillableRateResolver;
use App\Services\BudgetMonitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class TimeEntry extends Model
{
    use HasFactory;
    use HasPublicUuid;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            // Invoiced entries keep the amount they were billed at; linking and releasing them never re-prices.
            if ($entry->invoice_line_id !== null || $entry->getOriginal('invoice_line_id') !== null) {
                return;
            }

            self::deriveBillingValues($entry);
        });

        static::creating(function (self $entry): void {
            self::fillUuid($entry);
        });

        static::updating(function (self $entry): void {
            if ($entry->getOriginal('invoice_line_id') === null) {
                return;
            }

            $changed = array_diff(array_keys($entry->getDirty()), ['invoice_line_id', 'updated_at']);

            if ($changed !== []) {
                throw ValidationException::withMessages([
                    'time_entry' => __('This time entry is on an invoice and can\'t be changed. Void the invoice to unlock it.'),
                ]);
            }
        });

        static::saved(function (self $entry): void {
            $monitor = app(BudgetMonitor::class);
            $monitor->touched($entry->project_id);

            if ($entry->wasChanged('project_id') && $entry->getOriginal('project_id') !== null) {
                $monitor->touched((int) $entry->getOriginal('project_id'));
            }
        });

        static::deleted(function (self $entry): void {
            app(BudgetMonitor::class)->touched($entry->project_id);
        });

        static::restored(function (self $entry): void {
            app(BudgetMonitor::class)->touched($entry->project_id);
        });

        static::deleting(function (self $entry): void {
            if ($entry->isInvoiced()) {
                throw ValidationException::withMessages([
                    'time_entry' => __('This time entry is on an invoice and can\'t be deleted. Void the invoice to unlock it.'),
                ]);
            }
        });
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_line_id !== null;
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->whereNull('invoice_line_id');
    }

    protected $fillable = [
        'project_id',
        'project_stage_id',
        'user_id',
        'entry_date',
        'duration_minutes',
        'is_billable',
        'billable_amount',
        'notes',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_billable' => 'boolean',
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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
        $isBillable = (bool) ($entry->is_billable ?? false);

        $entry->duration_minutes = $durationMinutes;

        if (! $isBillable || $durationMinutes === 0) {
            $entry->billable_amount = 0;

            return;
        }

        $hourlyRate = app(BillableRateResolver::class)->forProjectId($entry->project_id);

        if ($hourlyRate <= 0.0) {
            $entry->billable_amount = 0;

            return;
        }

        $entry->billable_amount = round(($durationMinutes / 60) * $hourlyRate, 2);
    }
}
