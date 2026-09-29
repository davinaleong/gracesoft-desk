<?php

namespace App\Services;

use App\Contracts\CommitSummarizer;
use App\Support\SummaryResult;

/** No-op implementation used while AI is switched off or not configured. */
class NullCommitSummarizer implements CommitSummarizer
{
    public function summarize(array $payload): SummaryResult
    {
        return new SummaryResult(summary: '', suggestedStageName: null);
    }

    public function provider(): string
    {
        return 'null';
    }

    public function model(): string
    {
        return 'none';
    }
}
