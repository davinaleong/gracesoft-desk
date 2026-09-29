<?php

namespace App\Services;

use App\Mail\BudgetAlertMail;
use App\Models\BudgetAlert;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Measures project budget burn and raises each threshold alert once per budget revision.
 * Bound as a singleton so bulk writes (CSV import) can defer evaluation until they finish.
 */
class BudgetMonitor
{
    public const TYPE_NONE = 'none';

    public const TYPE_HOURS = 'hours';

    public const TYPE_AMOUNT = 'amount';

    public const TYPES = [self::TYPE_NONE, self::TYPE_HOURS, self::TYPE_AMOUNT];

    public const DEFAULT_THRESHOLDS = [50, 80, 100];

    private int $deferDepth = 0;

    /** @var array<int, true> */
    private array $pendingProjectIds = [];

    /**
     * Run a bulk write, then evaluate every touched project once.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function deferDuring(callable $callback): mixed
    {
        $this->deferDepth++;

        try {
            return $callback();
        } finally {
            $this->deferDepth--;

            if ($this->deferDepth === 0) {
                $projectIds = array_keys($this->pendingProjectIds);
                $this->pendingProjectIds = [];

                foreach ($projectIds as $projectId) {
                    $this->evaluateProjectId($projectId);
                }
            }
        }
    }

    public function touched(?int $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        if ($this->deferDepth > 0) {
            $this->pendingProjectIds[$projectId] = true;

            return;
        }

        $this->evaluateProjectId($projectId);
    }

    public function evaluateProjectId(int $projectId): void
    {
        $project = Project::query()->find($projectId);

        if ($project !== null) {
            $this->evaluate($project);
        }
    }

    /**
     * @return Collection<int, BudgetAlert> alerts raised by this evaluation, lowest threshold first
     */
    public function evaluate(Project $project): Collection
    {
        $raised = new Collection;

        if (! $this->hasBudget($project)) {
            return $raised;
        }

        $used = $this->used($project);
        $percent = $this->percentUsed($project, $used);

        $alreadyRaised = BudgetAlert::query()
            ->where('project_id', $project->id)
            ->where('budget_revision', $project->budget_revision)
            ->pluck('threshold')
            ->all();

        foreach ($this->thresholds($project) as $threshold) {
            if ($percent < $threshold || in_array($threshold, $alreadyRaised, true)) {
                continue;
            }

            try {
                $raised->push(BudgetAlert::query()->create([
                    'project_id' => $project->id,
                    'budget_revision' => $project->budget_revision,
                    'threshold' => $threshold,
                    'budget_type' => $project->budget_type,
                    'budget_value' => $project->budget_value,
                    'used_value' => round($used, 2),
                    'triggered_at' => now(),
                ]));
            } catch (QueryException) {
                // A concurrent evaluation raised it first; the unique index keeps it to one.
            }
        }

        foreach ($raised as $alert) {
            $this->notify($project, $alert);
        }

        return $raised;
    }

    public function hasBudget(Project $project): bool
    {
        return in_array($project->budget_type, [self::TYPE_HOURS, self::TYPE_AMOUNT], true)
            && (float) $project->budget_value > 0;
    }

    /**
     * Hours count every non-deleted entry, billable or not; money counts billable value only.
     */
    public function used(Project $project): float
    {
        $entries = $project->timeEntries();

        return $project->budget_type === self::TYPE_HOURS
            ? ((int) $entries->sum('duration_minutes')) / 60
            : (float) $entries->where('is_billable', true)->sum('billable_amount');
    }

    public function percentUsed(Project $project, ?float $used = null): float
    {
        if (! $this->hasBudget($project)) {
            return 0.0;
        }

        return round((($used ?? $this->used($project)) / (float) $project->budget_value) * 100, 2);
    }

    /**
     * @return array<int, int> ascending
     */
    public function thresholds(Project $project): array
    {
        $thresholds = array_values(array_unique(array_map('intval', (array) ($project->budget_thresholds ?: self::DEFAULT_THRESHOLDS))));
        sort($thresholds);

        return array_values(array_filter($thresholds, fn (int $threshold): bool => $threshold > 0 && $threshold <= 1000));
    }

    /**
     * Projects with a budget that have crossed at least their lowest threshold, most-burned first.
     *
     * @return Collection<int, array{project: Project, percent: float, used: float}>
     */
    public function atRisk(): Collection
    {
        return Project::query()
            ->whereIn('budget_type', [self::TYPE_HOURS, self::TYPE_AMOUNT])
            ->where('budget_value', '>', 0)
            ->get()
            ->map(function (Project $project): array {
                $used = $this->used($project);

                return ['project' => $project, 'percent' => $this->percentUsed($project, $used), 'used' => round($used, 2)];
            })
            ->filter(fn (array $row): bool => $row['percent'] >= ($this->thresholds($row['project'])[0] ?? 100))
            ->sortByDesc('percent')
            ->values();
    }

    private function notify(Project $project, BudgetAlert $alert): void
    {
        $recipients = User::query()->pluck('email')->filter()->all();

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new BudgetAlertMail($project, $alert));
            $alert->update(['notified_at' => now()]);
        } catch (Throwable $exception) {
            // The alert row stays (so it never double-fires); the bot endpoint still surfaces it.
            Log::warning('Budget alert email failed', ['alert' => $alert->uuid, 'error' => $exception->getMessage()]);
        }
    }
}
