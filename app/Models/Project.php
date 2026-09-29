<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\BudgetMonitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

        // A new budget (type or value) re-arms every threshold.
        static::updating(function (self $project): void {
            if ($project->isDirty(['budget_type', 'budget_value'])) {
                $project->budget_revision = (int) $project->getOriginal('budget_revision') + 1;
            }
        });

        static::saved(function (self $project): void {
            if ($project->wasChanged(['budget_type', 'budget_value', 'budget_thresholds']) || $project->wasRecentlyCreated) {
                app(BudgetMonitor::class)->touched($project->id);
            }
        });
    }

    protected $fillable = [
        'client_id',
        'code',
        'name',
        'status',
        'description',
        'starts_on',
        'ends_on',
        'is_billable',
        'hourly_rate',
        'ai_opt_out',
        'budget_type',
        'budget_value',
        'budget_thresholds',
        'github_repo',
        'github_branch',
        'github_connection_id',
        'github_webhook_id',
        'github_webhook_secret',
    ];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_billable' => 'boolean',
            'ai_opt_out' => 'boolean',
            'budget_value' => 'decimal:2',
            'budget_thresholds' => 'array',
            'budget_revision' => 'integer',
            'hourly_rate' => 'decimal:2',
            'github_webhook_id' => 'integer',
            'github_webhook_secret' => 'encrypted',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function githubConnection(): BelongsTo
    {
        return $this->belongsTo(GithubConnection::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function latestTimeEntry(): HasOne
    {
        return $this->hasOne(TimeEntry::class)->latestOfMany('entry_date');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function budgetAlerts(): HasMany
    {
        return $this->hasMany(BudgetAlert::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
