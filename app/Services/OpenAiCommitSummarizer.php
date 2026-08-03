<?php

namespace App\Services;

use App\Contracts\CommitSummarizer;
use App\Support\SummaryResult;
use Illuminate\Support\Facades\Http;

class OpenAiCommitSummarizer implements CommitSummarizer
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
    ) {}

    public function summarize(array $commits, array $stageNames): SummaryResult
    {
        $stageList = implode(', ', $stageNames);
        $commitLines = array_map(function (array $c): string {
            $stats = '';
            if ($c['additions'] !== null || $c['deletions'] !== null) {
                $stats = sprintf(' (+%d/-%d, %d files)', $c['additions'] ?? 0, $c['deletions'] ?? 0, $c['changed_files'] ?? 0);
            }

            return '- '.trim($c['message']).$stats;
        }, $commits);

        $commitText = implode("\n", $commitLines);

        $prompt = <<<PROMPT
You are a technical note writer for a software project time-tracking system.

Given the following git commits, write a concise 1–2 sentence plain-English summary suitable as a time entry note.
Also suggest which SDLC stage best fits these commits from this list: {$stageList}.

Commits:
{$commitText}

Respond with JSON only in this exact format:
{"summary":"…","stage":"…"}
PROMPT;

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 200,
                'response_format' => ['type' => 'json_object'],
            ]);

        $response->throw();

        $data = json_decode($response->json('choices.0.message.content', '{}'), true);

        return new SummaryResult(
            summary: $data['summary'] ?? '',
            suggestedStageName: $data['stage'] ?? null,
        );
    }
}
