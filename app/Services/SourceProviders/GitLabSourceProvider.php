<?php

namespace App\Services\SourceProviders;

use App\Contracts\SourceProvider;
use App\Models\GithubConnection;
use App\Support\ProviderAccount;
use App\Support\PushEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * GitLab.com or a self-hosted GitLab (services.gitlab.host).
 */
class GitLabSourceProvider implements SourceProvider
{
    use RefreshesOAuthTokens;

    public function key(): string
    {
        return 'gitlab';
    }

    public function label(): string
    {
        return 'GitLab';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gitlab.client_id'));
    }

    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('gitlab')->scopes(['api', 'read_user'])->redirect();
    }

    public function accountFromCallback(): ProviderAccount
    {
        $user = Socialite::driver('gitlab')->user();

        return new ProviderAccount(
            id: (string) $user->getId(),
            login: (string) $user->getNickname(),
            accessToken: $user->token,
            refreshToken: $user->refreshToken,
            expiresAt: $user->expiresIn ? now()->addSeconds((int) $user->expiresIn) : null,
            scopes: $user->approvedScopes ?? [],
        );
    }

    public function listRepositories(GithubConnection $connection): array
    {
        $projects = $this->paginate($connection, '/projects', ['membership' => 'true', 'simple' => 'true', 'order_by' => 'last_activity_at']);

        return array_map(fn (array $project): array => [
            'full_name' => $project['path_with_namespace'],
            'private' => ($project['visibility'] ?? 'private') !== 'public',
            'default_branch' => $project['default_branch'] ?? 'main',
        ], $projects);
    }

    public function listBranches(GithubConnection $connection, string $repo): array
    {
        return array_map(
            fn (array $branch): string => $branch['name'],
            $this->paginate($connection, '/projects/'.rawurlencode($repo).'/repository/branches'),
        );
    }

    public function registerWebhook(GithubConnection $connection, string $repo, string $url, string $secret): string
    {
        $response = $this->client($connection)->post('/projects/'.rawurlencode($repo).'/hooks', [
            'url' => $url,
            'push_events' => true,
            'token' => $secret,
            'enable_ssl_verification' => true,
        ])->throw();

        return (string) $response->json('id');
    }

    public function removeWebhook(GithubConnection $connection, string $repo, string $webhookId): void
    {
        $response = $this->client($connection)->delete('/projects/'.rawurlencode($repo).'/hooks/'.rawurlencode($webhookId));

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /** GitLab sends the webhook secret back verbatim in X-Gitlab-Token. */
    public function verifyRequest(Request $request, string $secret): bool
    {
        $token = (string) $request->header('X-Gitlab-Token', '');

        return $secret !== '' && $token !== '' && hash_equals($secret, $token);
    }

    public function isPushEvent(Request $request): bool
    {
        return $request->header('X-Gitlab-Event', '') === 'Push Hook';
    }

    public function parsePush(array $payload): PushEvent
    {
        $branch = str_replace('refs/heads/', '', (string) ($payload['ref'] ?? ''));

        $commits = array_map(function (array $commit) use ($branch): array {
            $paths = array_merge($commit['added'] ?? [], $commit['removed'] ?? [], $commit['modified'] ?? []);

            return PushEvent::commit([
                'sha' => $commit['id'] ?? '',
                'author_name' => $commit['author']['name'] ?? null,
                'author_email' => $commit['author']['email'] ?? null,
                'timestamp' => $commit['timestamp'] ?? null,
                'message' => $commit['message'] ?? '',
                'branch' => $branch,
                'files_changed' => count($paths),
                'file_paths' => array_slice(array_values(array_unique($paths)), 0, 100),
            ]);
        }, array_values(array_filter((array) ($payload['commits'] ?? []), fn ($commit): bool => is_array($commit) && ! empty($commit['id']))));

        return new PushEvent($branch, $commits);
    }

    /** Groups and subgroups: group/subgroup/project. */
    public function repoPattern(): string
    {
        return '/^[\w.\-]+(\/[\w.\-]+)+$/';
    }

    protected function tokenUrl(): string
    {
        return $this->host().'/oauth/token';
    }

    protected function refreshParameters(GithubConnection $connection): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $connection->refresh_token,
            'client_id' => (string) config('services.gitlab.client_id'),
            'client_secret' => (string) config('services.gitlab.client_secret'),
            'redirect_uri' => (string) config('services.gitlab.redirect'),
        ];
    }

    private function host(): string
    {
        return rtrim((string) (config('services.gitlab.host') ?: 'https://gitlab.com'), '/');
    }

    private function client(GithubConnection $connection): PendingRequest
    {
        return Http::withToken($this->accessToken($connection))
            ->acceptJson()
            ->timeout(20)
            ->baseUrl($this->host().'/api/v4');
    }

    /**
     * @param  array<string, string>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paginate(GithubConnection $connection, string $path, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $batch = (array) $this->client($connection)->get($path, $query + ['per_page' => 100, 'page' => $page])->throw()->json();
            $items = array_merge($items, $batch);
            $page++;
        } while (count($batch) === 100 && $page <= 50);

        return $items;
    }
}
