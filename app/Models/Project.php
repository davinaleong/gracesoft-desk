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
    public const BILLING_HOURLY = 'hourly';

    public const BILLING_FIXED_FEE = 'fixed_fee';

    public const BILLING_RETAINER = 'retainer';

    public const BILLING_MODELS = [self::BILLING_HOURLY, self::BILLING_FIXED_FEE, self::BILLING_RETAINER];

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
        'billing_model',
        'fixed_fee_total',
        'retainer_monthly_amount',
        'retainer_included_hours',
        'retainer_overage_rate',
        'retainer_rollover',
        'github_repo',
        'github_branch',
        'github_connection_id',
        'github_webhook_id',
        'github_webhook_secret',
        'source_provider',
        'source_webhook_ref',
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
            'fixed_fee_total' => 'decimal:2',
            'retainer_monthly_amount' => 'decimal:2',
            'retainer_included_hours' => 'decimal:2',
            'retainer_overage_rate' => 'decimal:2',
            'retainer_rollover' => 'boolean',
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

    /**
     * The Git host the linked repository lives on. Rows linked before multi-provider support are GitHub.
     */
    public function sourceProviderKey(): string
    {
        return $this->source_provider ?: 'github';
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function retainerPeriods(): HasMany
    {
        return $this->hasMany(RetainerPeriod::class);
    }

    public function billingModel(): string
    {
        return $this->billing_model ?: self::BILLING_HOURLY;
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
