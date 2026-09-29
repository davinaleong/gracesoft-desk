<?php

namespace App\Services;

use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Builds the Monday–Sunday week of pending commits (in the system timezone) and turns them into time entries.
 */
class WeeklyTimesheetService
{
    public const DEFAULT_MINUTES = 15;

    public function __construct(private CommitStageMatcherService $matcher) {}

    /**
     * Monday 00:00 of the week containing the given day, in the system timezone.
     */
    public function weekStart(?string $day = null): CarbonImmutable
    {
        $timezone = (string) config('app.timezone');

        try {
            $date = $day !== null && $day !== '' ? CarbonImmutable::parse($day, $timezone) : CarbonImmutable::now($timezone);
        } catch (\Throwable) {
            $date = CarbonImmutable::now($timezone);
        }

        return $date->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /**
     * Pending commits committed during the week, oldest first.
     *
     * @return Collection<int, CommitTimeEntry>
     */
    public function pendingForWeek(CarbonImmutable $weekStart): Collection
    {
        return $this->inWeek(CommitTimeEntry::query()->pending(), $weekStart)
            ->with(['project', 'aiSuggestedStage'])
            ->orderBy('committed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CommitTimeEntry>
     */
    public function dismissedForWeek(CarbonImmutable $weekStart): Collection
    {
        return $this->inWeek(CommitTimeEntry::query()->where('status', CommitTimeEntry::STATUS_DISMISSED), $weekStart)
            ->with('project')
            ->orderBy('committed_at')
            ->get();
    }

    /**
     * Pending commits grouped by local day, then by project.
     *
     * @param  Collection<int, CommitTimeEntry>  $commits
     * @return array<string, array<int, array{project: Project, commits: array<int, array{commit: CommitTimeEntry, stage: ?ProjectStage, summary: string, minutes: int}>}>>
     */
    public function groupByDayAndProject(Collection $commits): array
    {
        $days = [];

        foreach ($commits as $commit) {
            $day = $commit->committed_at?->toDateString() ?? 'undated';
            $projectId = $commit->project_id;

            $days[$day][$projectId] ??= ['project' => $commit->project, 'commits' => []];
            $days[$day][$projectId]['commits'][] = [
                'commit' => $commit,
                'stage' => $this->suggestedStage($commit),
                'summary' => $this->suggestedSummary($commit),
                'minutes' => self::DEFAULT_MINUTES,
            ];
        }

        ksort($days);

        return array_map('array_values', $days);
    }

    public function suggestedStage(CommitTimeEntry $commit): ?ProjectStage
    {
        return $this->matcher->match($commit->message, (string) $commit->branch) ?? $commit->aiSuggestedStage;
    }

    public function suggestedSummary(CommitTimeEntry $commit): string
    {
        return $commit->ai_summary ?: Str::limit(trim(strtok($commit->message, "\n") ?: $commit->message), 200, '');
    }

    /**
     * Minutes already logged in the week (by entry date), for comparison with the weekly target.
     */
    public function loggedMinutes(CarbonImmutable $weekStart): int
    {
        return (int) TimeEntry::query()
            ->whereBetween('entry_date', [$weekStart->toDateString(), $weekStart->addDays(6)->toDateString()])
            ->sum('duration_minutes');
    }

    public function weeklyTargetHours(): ?float
    {
        $value = SystemSetting::query()->where('key', 'weekly_hours_target')->value('value');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /**
     * Convert pending commits into time entries in one transaction.
     *
     * @param  array<string, array{minutes?: int|string|null, stage_uuid?: string|null, notes?: string|null}>  $rows  keyed by commit uuid
     * @return int number of time entries created
     */
    public function convert(array $rows, bool $groupByDayAndProject, bool $isBillable, ?int $userId): int
    {
        return DB::transaction(function () use ($rows, $groupByDayAndProject, $isBillable, $userId): int {
            $commits = CommitTimeEntry::query()
                ->whereIn('uuid', array_keys($rows))
                ->lockForUpdate()
                ->with(['project', 'aiSuggestedStage'])
                ->orderBy('committed_at')
                ->orderBy('id')
                ->get();

            $pending = $commits->where('status', CommitTimeEntry::STATUS_PENDING);

            // A repeated submit finds nothing pending and creates nothing.
            if ($pending->isEmpty()) {
                return 0;
            }

            if ($pending->count() !== count($rows)) {
                throw ValidationException::withMessages([
                    'commits' => __('Some of the selected commits were already converted or dismissed. Refresh the week and try again.'),
                ]);
            }

            $stageIds = ProjectStage::query()->pluck('id', 'uuid');

            $groups = $groupByDayAndProject
                ? $pending->groupBy(fn (CommitTimeEntry $c): string => ($c->committed_at?->toDateString() ?? 'undated').'|'.$c->project_id)
                : $pending->map(fn (CommitTimeEntry $c): Collection => new Collection([$c]));

            $created = 0;

            foreach ($groups as $group) {
                /** @var CommitTimeEntry $first */
                $first = $group->first();
                $firstRow = $rows[$first->uuid] ?? [];

                $minutes = $group->sum(fn (CommitTimeEntry $c): int => CommitStageMatcherService::snapDuration(
                    (int) (($rows[$c->uuid]['minutes'] ?? null) ?: self::DEFAULT_MINUTES)
                ));

                $stageId = filled($firstRow['stage_uuid'] ?? null)
                    ? $stageIds->get($firstRow['stage_uuid'])
                    : $this->suggestedStage($first)?->id;

                $notes = $group->map(fn (CommitTimeEntry $c): string => trim((string) (($rows[$c->uuid]['notes'] ?? null) ?: $this->suggestedSummary($c))))
                    ->filter()
                    ->unique()
                    ->implode('; ');

                $entry = TimeEntry::query()->create([
                    'project_id' => $first->project_id,
                    'project_stage_id' => $stageId,
                    'user_id' => $userId,
                    'entry_date' => ($first->committed_at ?? now())->toDateString(),
                    'duration_minutes' => $minutes,
                    'is_billable' => $isBillable && (bool) $first->project?->is_billable,
                    'notes' => Str::limit($notes, 2000, ''),
                ]);

                $ids = $group->pluck('id');

                CommitTimeEntry::query()->whereIn('id', $ids)->update([
                    'status' => 'approved',
                    'converted_time_entry_id' => $entry->id,
                ]);

                // Commits squashed into these anchors follow them.
                CommitTimeEntry::query()->whereIn('squashed_into', $ids)->update([
                    'status' => 'approved',
                    'converted_time_entry_id' => $entry->id,
                ]);

                $created++;
            }

            return $created;
        });
    }

    /**
     * @param  Builder<CommitTimeEntry>  $query
     * @return Builder<CommitTimeEntry>
     */
    private function inWeek(Builder $query, CarbonImmutable $weekStart): Builder
    {
        return $query->whereBetween('committed_at', [
            $weekStart->utc()->format('Y-m-d H:i:s'),
            $weekStart->addWeek()->subSecond()->utc()->format('Y-m-d H:i:s'),
        ]);
    }
}
