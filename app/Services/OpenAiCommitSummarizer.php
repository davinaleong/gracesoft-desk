<?php

namespace App\Services;

use App\Contracts\CommitSummarizer;
use App\Services\Ai\SummaryPrompt;
use App\Support\SummaryResult;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI chat completions. Also drives any OpenAI-compatible endpoint (Ollama, LM Studio, vLLM…)
 * when constructed with its base URL; the API key is optional for local endpoints.
 */
class OpenAiCommitSummarizer implements CommitSummarizer
{
    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly string $providerName = 'openai',
    ) {}

    public function summarize(array $payload): SummaryResult
    {
        $request = Http::timeout(30)->connectTimeout(5)->acceptJson();

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        $body = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => SummaryPrompt::build($payload)]],
            'max_tokens' => 300,
        ];

        if ($this->providerName === 'openai') {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = $request->post(rtrim($this->baseUrl, '/').'/chat/completions', $body)->throw();

        return SummaryPrompt::parse((string) $response->json('choices.0.message.content', ''));
    }

    public function provider(): string
    {
        return $this->providerName;
    }

    public function model(): string
    {
        return $this->model;
    }
}
