<?php

namespace App\Services;

use App\Models\CommitTimeEntry;
use App\Models\Project;

class PushSizeThresholdService
{
    /** How many recent pushes feed the rolling average/stddev. */
    private const ROLLING_WINDOW = 10;

    /** Minimum push history required before trusting avg + 2×stddev. */
    private const MIN_PUSHES_FOR_STATS = 3;

    /** Cold-start threshold when there isn't enough push history yet. */
    private const FALLBACK_THRESHOLD = 10;

    public function isLargePush(Project $project, int $commitCount): bool
    {
        return $commitCount > $this->threshold($project);
    }

    public function threshold(Project $project): float
    {
        $counts = $this->recentPushCommitCounts($project);

        if (count($counts) < self::MIN_PUSHES_FOR_STATS) {
            return (float) self::FALLBACK_THRESHOLD;
        }

        $mean = array_sum($counts) / count($counts);
        $variance = array_sum(array_map(
            fn (int $count): float => ($count - $mean) ** 2,
            $counts
        )) / count($counts);

        return $mean + (2 * sqrt($variance));
    }

    /** @return array<int, int> */
    private function recentPushCommitCounts(Project $project): array
    {
        return CommitTimeEntry::query()
            ->where('project_id', $project->id)
            ->whereNotNull('push_batch_uuid')
            ->select('push_batch_uuid')
            ->selectRaw('COUNT(*) as commit_count')
            ->selectRaw('MAX(created_at) as last_ingested_at')
            ->groupBy('push_batch_uuid')
            ->orderByDesc('last_ingested_at')
            ->limit(self::ROLLING_WINDOW)
            ->get()
            ->map(fn ($row): int => (int) $row->commit_count)
            ->all();
    }
}
