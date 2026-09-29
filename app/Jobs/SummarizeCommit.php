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

class SummarizeCommit implements ShouldQueue
{
    use Queueable;

    /** Provider timeouts and 5xx responses are retried with backoff, then the job gives up quietly. */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly CommitTimeEntry $commit) {}

    public function handle(CommitSummaryGateway $gateway): void
    {
        // Skip if a keyword match already covers this commit — no need for AI.
        $matcher = new CommitStageMatcherService;
        if ($matcher->match($this->commit->message, $this->commit->branch ?? '')) {
            return;
        }

        $stages = ProjectStage::query()->orderBy('sort_order')->get();

        if ($stages->isEmpty()) {
            return;
        }

        $result = $gateway->summarize(collect([$this->commit]), $stages, 'commit_summary', $this->commit->project);

        if ($result === null) {
            return;
        }

        $this->commit->update([
            'ai_summary' => $result->summary ?: null,
            'ai_suggested_stage_id' => $stages->firstWhere('name', $result->suggestedStageName)?->id,
        ]);
    }

    /**
     * The commit simply stays pending without a summary.
     */
    public function failed(?Throwable $exception): void
    {
        Log::info('AI commit summary gave up', ['commit' => $this->commit->uuid, 'error' => $exception?->getMessage()]);
    }
}
