<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\CommitIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IngestLargePushBatch implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $commits
     */
    public function __construct(
        public readonly Project $project,
        public readonly string $branch,
        public readonly array $commits,
        public readonly string $pushBatchUuid,
    ) {}

    public function handle(CommitIngestionService $ingestion): void
    {
        $ingestion->ingest($this->project, $this->branch, $this->commits, $this->pushBatchUuid, true);
    }
}
