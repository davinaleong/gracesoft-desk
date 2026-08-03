<?php

namespace App\Contracts;

use App\Support\SummaryResult;

interface CommitSummarizer
{
    /**
     * Generate a human-readable summary and suggest an SDLC stage for one or more commits.
     *
     * @param  array<int, array{message: string, additions: int|null, deletions: int|null, changed_files: int|null}>  $commits
     * @param  array<int, string>  $stageNames  Available stage names the LLM should pick from.
     */
    public function summarize(array $commits, array $stageNames): SummaryResult;
}
