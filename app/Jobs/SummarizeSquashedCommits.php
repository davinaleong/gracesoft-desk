<?php

namespace App\Jobs;

use App\Models\CommitTimeEntry;
use App\Models\ProjectStage;
use App\Services\Ai\CommitSummaryGateway;
use App\Services\CommitStageMatcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SummarizeSquashedCommits implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly CommitTimeEntry $anchor) {}

    public function handle(CommitSummaryGateway $gateway): void
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

        if ($stages->isEmpty()) {
            return;
        }

        $result = $gateway->summarize($group, $stages, 'squash_summary', $this->anchor->project);

        if ($result === null) {
            return;
        }

        $this->anchor->update([
            'ai_summary' => $result->summary ?: null,
            'ai_suggested_stage_id' => $stages->firstWhere('name', $result->suggestedStageName)?->id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::info('AI squash summary gave up', ['commit' => $this->anchor->uuid, 'error' => $exception?->getMessage()]);
    }
}
