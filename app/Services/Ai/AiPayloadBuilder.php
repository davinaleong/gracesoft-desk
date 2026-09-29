<?php

namespace App\Services\Ai;

use App\Models\CommitTimeEntry;
use App\Models\ProjectStage;
use Illuminate\Support\Collection;

/**
 * Builds the only data an AI provider ever sees. Allow-list: commit message, branch,
 * changed-file count, stage names and keywords; file paths only when switched on.
 * Never diffs, author names or author emails.
 */
class AiPayloadBuilder
{
    public function __construct(private Redactor $redactor, private AiSettings $settings) {}

    /**
     * @param  iterable<CommitTimeEntry>  $commits
     * @param  Collection<int, ProjectStage>  $stages
     * @return array{commits: array<int, array<string, mixed>>, stages: array<int, array{name: string, keywords: array<int, string>}>}
     */
    public function forCommits(iterable $commits, Collection $stages): array
    {
        $includePaths = $this->settings->sendFilePaths();

        $commitRows = [];

        foreach ($commits as $commit) {
            $row = [
                'message' => $this->redactor->redact((string) $commit->message),
                'branch' => $commit->branch !== null ? $this->redactor->redact($commit->branch) : null,
                'changed_files' => $commit->changed_files,
            ];

            if ($includePaths) {
                $row['file_paths'] = array_values(array_map(
                    fn (string $path): string => $this->redactor->redact($path),
                    (array) ($commit->file_paths ?? []),
                ));
            }

            $commitRows[] = $row;
        }

        return [
            'commits' => $commitRows,
            'stages' => $stages->map(fn (ProjectStage $stage): array => [
                'name' => $stage->name,
                'keywords' => array_values((array) ($stage->keywords ?? [])),
            ])->values()->all(),
        ];
    }
}
