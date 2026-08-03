<?php

namespace App\Jobs;

use App\Contracts\CommitSummarizer;
use App\Models\CommitTimeEntry;
use App\Models\ProjectStage;
use App\Services\CommitStageMatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SummarizeSquashedCommits implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CommitTimeEntry $anchor) {}

    public function handle(CommitSummarizer $summarizer): void
    {
        $group = CommitTimeEntry::query()
            ->where('squashed_into', $this->anchor->id)
            ->get()
            ->push($this->anchor);

        $combinedText = $group->map(fn (CommitTimeEntry $c): string => $c->message.' '.($c->branch ?? ''))->implode(' ');

        // Keyword rules still win over AI, even across a squashed group.
        $matcher = new CommitStageMatcherService;
        if ($matcher->match($combinedText, '')) {
            return;
        }

        $stages = ProjectStage::query()->orderBy('sort_order')->get();
        $stageNames = $stages->pluck('name')->all();

        if (empty($stageNames)) {
            return;
        }

        $result = $summarizer->summarize(
            $group->map(fn (CommitTimeEntry $c): array => [
                'message' => $c->message,
                'additions' => $c->additions,
                'deletions' => $c->deletions,
                'changed_files' => $c->changed_files,
            ])->all(),
            $stageNames
        );

        $suggestedStage = $stages->firstWhere('name', $result->suggestedStageName);

        $this->anchor->update([
            'ai_summary' => $result->summary ?: null,
            'ai_suggested_stage_id' => $suggestedStage?->id,
        ]);
    }
}
