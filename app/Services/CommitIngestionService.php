<?php

namespace App\Services;

use App\Jobs\SummarizeCommit;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use Carbon\Carbon;

class CommitIngestionService
{
    /**
     * Upsert a push's commits as pending commit_time_entries rows.
     *
     * @param  array<int, array<string, mixed>>  $commits
     */
    public function ingest(Project $project, string $branch, array $commits, string $pushBatchUuid, bool $fromLargeBatch): void
    {
        if (empty($commits)) {
            return;
        }

        $firstCommit = $commits[0] ?? [];
        $statsInPayload = isset($firstCommit['added'], $firstCommit['removed'], $firstCommit['modified']);

        foreach ($commits as $commit) {
            $sha = $commit['id'] ?? null;

            if (! $sha) {
                continue;
            }

            $changedFiles = null;

            if ($statsInPayload) {
                $changedFiles = count($commit['added'] ?? [])
                    + count($commit['removed'] ?? [])
                    + count($commit['modified'] ?? []);
            }

            $entry = CommitTimeEntry::updateOrCreate(
                ['project_id' => $project->id, 'sha' => $sha],
                [
                    'branch' => $branch,
                    'push_batch_uuid' => $pushBatchUuid,
                    'from_large_batch' => $fromLargeBatch,
                    'author_name' => $commit['author']['name'] ?? null,
                    'author_email' => $commit['author']['email'] ?? null,
                    'committed_at' => isset($commit['timestamp'])
                        ? Carbon::parse($commit['timestamp'])
                        : null,
                    'message' => $commit['message'] ?? '',
                    'additions' => null,
                    'deletions' => null,
                    'changed_files' => $changedFiles,
                    'status' => 'pending',
                ]
            );

            // Large batches skip per-commit AI: they're surfaced as a suggested
            // squash group instead, and SummarizeSquashedCommits covers the AI
            // note once the group is actually squashed.
            if ($entry->wasRecentlyCreated && ! $fromLargeBatch) {
                SummarizeCommit::dispatch($entry);
            }
        }
    }
}
