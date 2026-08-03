<?php

namespace App\Jobs;

use App\Contracts\CommitSummarizer;
use App\Models\CommitTimeEntry;
use App\Models\ProjectStage;
use App\Services\CommitStageMatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SummarizeCommit implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CommitTimeEntry $commit) {}

    public function handle(CommitSummarizer $summarizer): void
    {
        // Skip if a keyword match already covers this commit — no need for AI.
        $matcher = new CommitStageMatcherService;
        if ($matcher->match($this->commit->message, $this->commit->branch ?? '')) {
            return;
        }

        $stages = ProjectStage::query()->orderBy('sort_order')->get();
        $stageNames = $stages->pluck('name')->all();

        if (empty($stageNames)) {
            return;
        }

        $result = $summarizer->summarize(
            [[
                'message' => $this->commit->message,
                'additions' => $this->commit->additions,
                'deletions' => $this->commit->deletions,
                'changed_files' => $this->commit->changed_files,
            ]],
            $stageNames
        );

        $suggestedStage = $stages->firstWhere('name', $result->suggestedStageName);

        $this->commit->update([
            'ai_summary' => $result->summary ?: null,
            'ai_suggested_stage_id' => $suggestedStage?->id,
        ]);
    }
}
