<?php

namespace App\Services;

use App\Jobs\SummarizeCommit;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Support\PushEvent;
use Carbon\Carbon;

class CommitIngestionService
{
    /**
     * Upsert a push's commits as pending commit_time_entries rows, de-duplicated on provider + repo + SHA.
     *
     * @param  array<int, array<string, mixed>>  $commits  normalised commits (see PushEvent)
     */
    public function ingest(Project $project, string $branch, array $commits, string $pushBatchUuid, bool $fromLargeBatch): void
    {
        if (empty($commits)) {
            return;
        }

        $provider = $project->sourceProviderKey();
        $repo = $project->github_repo;

        foreach ($commits as $commit) {
            $commit = PushEvent::commit($commit);

            if ($commit['sha'] === '') {
                continue;
            }

            // Rows ingested before provider/repo were recorded are matched on project + SHA and backfilled.
            $entry = CommitTimeEntry::query()
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('provider', $provider)->where('repo', $repo))
                    ->orWhere(fn ($q) => $q->where('project_id', $project->id)->whereNull('repo')))
                ->where('sha', $commit['sha'])
                ->first() ?? new CommitTimeEntry;

            $entry->fill(
                [
                    'provider' => $provider,
                    'repo' => $repo,
                    'sha' => $commit['sha'],
                    'project_id' => $project->id,
                    'branch' => $commit['branch'] !== '' ? $commit['branch'] : $branch,
                    'push_batch_uuid' => $pushBatchUuid,
                    'from_large_batch' => $fromLargeBatch,
                    'author_name' => $commit['author_name'],
                    'author_email' => $commit['author_email'],
                    'committed_at' => $commit['timestamp'] !== null ? Carbon::parse($commit['timestamp']) : null,
                    'message' => $commit['message'],
                    'additions' => null,
                    'deletions' => null,
                    'changed_files' => $commit['files_changed'],
                    'file_paths' => $commit['file_paths'],
                    'status' => 'pending',
                ]
            )->save();

            // Large batches skip per-commit AI: they're surfaced as a suggested
            // squash group instead, and SummarizeSquashedCommits covers the AI
            // note once the group is actually squashed.
            if ($entry->wasRecentlyCreated && ! $fromLargeBatch) {
                SummarizeCommit::dispatch($entry);
            }
        }
    }
}
