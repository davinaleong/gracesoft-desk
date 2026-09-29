<?php

namespace App\Services\Ai;

use App\Contracts\CommitSummarizer;
use App\Models\AiRequest;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Services\NullCommitSummarizer;
use App\Support\SummaryResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single door AI traffic goes through: checks the switch and the project opt-out,
 * sends only the allow-listed payload, and logs every call (hash and length, never the payload).
 */
class CommitSummaryGateway
{
    public function __construct(
        private CommitSummarizer $summarizer,
        private AiSettings $settings,
        private AiPayloadBuilder $payloads,
    ) {}

    /**
     * @param  Collection<int, CommitTimeEntry>  $commits
     * @param  Collection<int, ProjectStage>  $stages
     */
    public function summarize(Collection $commits, Collection $stages, string $purpose, ?Project $project): ?SummaryResult
    {
        if (! $this->settings->enabled() || $this->summarizer instanceof NullCommitSummarizer) {
            return null;
        }

        if ($project?->ai_opt_out) {
            return null;
        }

        $payload = $this->payloads->forCommits($commits, $stages);
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $startedAt = microtime(true);

        $log = fn (string $outcome, ?string $error = null) => AiRequest::query()->create([
            'requested_at' => now(),
            'provider' => $this->summarizer->provider(),
            'model' => $this->summarizer->model(),
            'purpose' => $purpose,
            'project_id' => $project?->id,
            'payload_hash' => hash('sha256', $json),
            'payload_length' => strlen($json),
            'outcome' => $outcome,
            'error' => $error,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        try {
            $result = $this->summarizer->summarize($payload);
        } catch (Throwable $exception) {
            $log(AiRequest::OUTCOME_ERROR, Str::limit(class_basename($exception).': '.$exception->getMessage(), 250, ''));

            throw $exception;
        }

        $log(AiRequest::OUTCOME_SUCCESS);

        return $result;
    }
}
