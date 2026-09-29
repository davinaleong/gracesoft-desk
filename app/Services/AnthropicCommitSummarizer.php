<?php

namespace App\Services;

use App\Contracts\CommitSummarizer;
use App\Services\Ai\SummaryPrompt;
use App\Support\SummaryResult;
use Illuminate\Support\Facades\Http;

/**
 * Claude via the Anthropic Messages API (raw HTTP, like the other drivers, so no extra SDK dependency).
 */
class AnthropicCommitSummarizer implements CommitSummarizer
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const API_VERSION = '2023-06-01';

    /**
     * Models with safety classifiers that can decline; for these we opt into server-side fallbacks.
     *
     * @var array<int, string>
     */
    private const FALLBACK_MODELS = ['claude-opus-5', 'claude-fable-5-1'];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-5',
    ) {}

    public function summarize(array $payload): SummaryResult
    {
        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ];

        $body = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => SummaryPrompt::build($payload)]],
        ];

        if (in_array($this->model, self::FALLBACK_MODELS, true)) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks'] = 'default';
            $body['output_config'] = ['effort' => 'low'];
        }

        $response = Http::timeout(60)->connectTimeout(5)->acceptJson()
            ->withHeaders($headers)
            ->post(self::ENDPOINT, $body)
            ->throw();

        if ($response->json('stop_reason') === 'refusal') {
            return new SummaryResult(summary: '', suggestedStageName: null);
        }

        $text = collect((array) $response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return SummaryPrompt::parse($text);
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }
}
