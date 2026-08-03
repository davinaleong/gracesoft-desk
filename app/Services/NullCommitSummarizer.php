<?php

namespace App\Services;

use App\Contracts\CommitSummarizer;
use App\Support\SummaryResult;

/** No-op implementation used when no AI provider is configured. */
class NullCommitSummarizer implements CommitSummarizer
{
    public function summarize(array $commits, array $stageNames): SummaryResult
    {
        return new SummaryResult(summary: '', suggestedStageName: null);
    }
}
