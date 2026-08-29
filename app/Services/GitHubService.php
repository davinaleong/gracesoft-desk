<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(private readonly string $token) {}

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->baseUrl(self::API_BASE);
    }

    /** @return array<int, array{full_name: string, private: bool, default_branch: string}> */
    public function listRepositories(): array
    {
        $repos = [];
        $page = 1;

        do {
            $response = $this->client()->get('/user/repos', [
                'per_page' => 100,
                'page' => $page,
                'sort' => 'updated',
                'affiliation' => 'owner,collaborator,organization_member',
            ]);

            $response->throw();
            $batch = $response->json();
            $repos = array_merge($repos, $batch);
            $page++;
        } while (count($batch) === 100);

        return array_map(fn (array $r) => [
            'full_name' => $r['full_name'],
            'private' => $r['private'],
            'default_branch' => $r['default_branch'] ?? 'main',
        ], $repos);
    }

    /** @return array<int, string> */
    public function listBranches(string $repo): array
    {
        $branches = [];
        $page = 1;

        do {
            $response = $this->client()->get("/repos/{$repo}/branches", [
                'per_page' => 100,
                'page' => $page,
            ]);

            $response->throw();
            $batch = $response->json();
            $branches = array_merge($branches, $batch);
            $page++;
        } while (count($batch) === 100);

        return array_map(fn (array $b) => $b['name'], $branches);
    }

    /** @param array<string, mixed> $config */
    public function registerWebhook(string $repo, string $payloadUrl, string $secret, array $config = []): int
    {
        $response = $this->client()->post("/repos/{$repo}/hooks", [
            'name' => 'web',
            'active' => true,
            'events' => ['push'],
            'config' => array_merge([
                'url' => $payloadUrl,
                'content_type' => 'json',
                'secret' => $secret,
                'insecure_ssl' => '0',
            ], $config),
        ]);

        $response->throw();

        return (int) $response->json('id');
    }

    public function removeWebhook(string $repo, int $webhookId): void
    {
        // A 404 means it was already removed on GitHub's side — treat as success.
        $response = $this->client()->delete("/repos/{$repo}/hooks/{$webhookId}");

        if ($response->status() !== 404) {
            $response->throw();
        }
    }
}
