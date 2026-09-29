<?php

namespace App\Services\SourceProviders;

use App\Contracts\SourceProvider;
use App\Models\GithubConnection;
use App\Services\GitHubService;
use App\Support\ProviderAccount;
use App\Support\PushEvent;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class GitHubSourceProvider implements SourceProvider
{
    public function key(): string
    {
        return 'github';
    }

    public function label(): string
    {
        return 'GitHub';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.github.client_id'));
    }

    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('github')->scopes(['repo', 'read:user'])->redirect();
    }

    public function accountFromCallback(): ProviderAccount
    {
        $user = Socialite::driver('github')->user();

        return new ProviderAccount(
            id: (string) $user->getId(),
            login: (string) $user->getNickname(),
            accessToken: $user->token,
            scopes: $user->approvedScopes ?? [],
        );
    }

    /** GitHub OAuth app tokens don't expire. */
    public function accessToken(GithubConnection $connection): string
    {
        return (string) $connection->access_token;
    }

    public function listRepositories(GithubConnection $connection): array
    {
        return $this->api($connection)->listRepositories();
    }

    public function listBranches(GithubConnection $connection, string $repo): array
    {
        return $this->api($connection)->listBranches($repo);
    }

    public function registerWebhook(GithubConnection $connection, string $repo, string $url, string $secret): string
    {
        return (string) $this->api($connection)->registerWebhook($repo, $url, $secret);
    }

    public function removeWebhook(GithubConnection $connection, string $repo, string $webhookId): void
    {
        $this->api($connection)->removeWebhook($repo, (int) $webhookId);
    }

    public function verifyRequest(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        return $secret !== '' && $signature !== ''
            && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function isPushEvent(Request $request): bool
    {
        return $request->header('X-GitHub-Event', '') === 'push';
    }

    public function parsePush(array $payload): PushEvent
    {
        $branch = str_replace('refs/heads/', '', (string) ($payload['ref'] ?? ''));

        $commits = array_map(function (array $commit) use ($branch): array {
            $hasStats = isset($commit['added'], $commit['removed'], $commit['modified']);
            $paths = $hasStats ? array_merge($commit['added'], $commit['removed'], $commit['modified']) : null;

            return PushEvent::commit([
                'sha' => $commit['id'] ?? '',
                'author_name' => $commit['author']['name'] ?? null,
                'author_email' => $commit['author']['email'] ?? null,
                'timestamp' => $commit['timestamp'] ?? null,
                'message' => $commit['message'] ?? '',
                'branch' => $branch,
                'files_changed' => $paths === null ? null : count($paths),
                'file_paths' => $paths === null ? null : array_slice(array_values(array_unique($paths)), 0, 100),
            ]);
        }, array_values(array_filter((array) ($payload['commits'] ?? []), fn ($commit): bool => is_array($commit) && ! empty($commit['id']))));

        return new PushEvent($branch, $commits);
    }

    public function repoPattern(): string
    {
        return '/^[^\/]+\/[^\/]+$/';
    }

    private function api(GithubConnection $connection): GitHubService
    {
        return new GitHubService($this->accessToken($connection));
    }
}
