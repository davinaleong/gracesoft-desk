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
 * Bitbucket Cloud.
 */
class BitbucketSourceProvider implements SourceProvider
{
    use RefreshesOAuthTokens;

    private const API_BASE = 'https://api.bitbucket.org/2.0';

    public function key(): string
    {
        return 'bitbucket';
    }

    public function label(): string
    {
        return 'Bitbucket';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.bitbucket.client_id'));
    }

    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('bitbucket')->scopes(['account', 'repository', 'webhook'])->redirect();
    }

    public function accountFromCallback(): ProviderAccount
    {
        $user = Socialite::driver('bitbucket')->user();

        return new ProviderAccount(
            id: (string) $user->getId(),
            login: (string) ($user->getNickname() ?: $user->getName()),
            accessToken: $user->token,
            refreshToken: $user->refreshToken,
            expiresAt: $user->expiresIn ? now()->addSeconds((int) $user->expiresIn) : null,
            scopes: $user->approvedScopes ?? [],
        );
    }

    public function listRepositories(GithubConnection $connection): array
    {
        $repos = $this->paginate($connection, self::API_BASE.'/repositories?role=member&pagelen=100&sort=-updated_on');

        return array_map(fn (array $repo): array => [
            'full_name' => $repo['full_name'],
            'private' => (bool) ($repo['is_private'] ?? true),
            'default_branch' => $repo['mainbranch']['name'] ?? 'main',
        ], $repos);
    }

    public function listBranches(GithubConnection $connection, string $repo): array
    {
        return array_map(
            fn (array $branch): string => $branch['name'],
            $this->paginate($connection, self::API_BASE.'/repositories/'.$repo.'/refs/branches?pagelen=100'),
        );
    }

    public function registerWebhook(GithubConnection $connection, string $repo, string $url, string $secret): string
    {
        $response = $this->client($connection)->post(self::API_BASE.'/repositories/'.$repo.'/hooks', [
            'description' => 'GraceSoft Desk',
            'url' => $url,
            'active' => true,
            'secret' => $secret,
            'events' => ['repo:push'],
        ])->throw();

        return (string) $response->json('uuid');
    }

    public function removeWebhook(GithubConnection $connection, string $repo, string $webhookId): void
    {
        $response = $this->client($connection)->delete(self::API_BASE.'/repositories/'.$repo.'/hooks/'.rawurlencode($webhookId));

        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /** Bitbucket signs webhook bodies with the secret: X-Hub-Signature: sha256=<hmac>. */
    public function verifyRequest(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature', '');

        return $secret !== '' && $signature !== ''
            && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function isPushEvent(Request $request): bool
    {
        return $request->header('X-Event-Key', '') === 'repo:push';
    }

    /**
     * Bitbucket nests commits per ref change; only branch updates count, and it sends no file lists.
     */
    public function parsePush(array $payload): PushEvent
    {
        $branch = '';
        $commits = [];

        foreach ((array) ($payload['push']['changes'] ?? []) as $change) {
            $new = $change['new'] ?? null;

            if (! is_array($new) || ($new['type'] ?? null) !== 'branch') {
                continue;
            }

            $branch = (string) ($new['name'] ?? '');

            // Bitbucket lists newest first; ingest oldest first like the other providers.
            foreach (array_reverse((array) ($change['commits'] ?? [])) as $commit) {
                if (empty($commit['hash'])) {
                    continue;
                }

                [$name, $email] = $this->parseRawAuthor((string) ($commit['author']['raw'] ?? ''));

                $commits[] = PushEvent::commit([
                    'sha' => $commit['hash'],
                    'author_name' => $name ?? ($commit['author']['user']['display_name'] ?? null),
                    'author_email' => $email,
                    'timestamp' => $commit['date'] ?? null,
                    'message' => $commit['message'] ?? '',
                    'branch' => $branch,
                    'files_changed' => null,
                    'file_paths' => null,
                ]);
            }
        }

        return new PushEvent($branch, $commits);
    }

    public function repoPattern(): string
    {
        return '/^[\w.\-]+\/[\w.\-]+$/';
    }

    protected function tokenUrl(): string
    {
        return 'https://bitbucket.org/site/oauth2/access_token';
    }

    protected function refreshParameters(GithubConnection $connection): array
    {
        return [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $connection->refresh_token,
        ];
    }

    protected function refreshRequest(): PendingRequest
    {
        return Http::asForm()->acceptJson()->timeout(20)
            ->withBasicAuth((string) config('services.bitbucket.client_id'), (string) config('services.bitbucket.client_secret'));
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseRawAuthor(string $raw): array
    {
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', trim($raw), $match) === 1) {
            return [trim($match[1]) ?: null, $match[2]];
        }

        return [trim($raw) ?: null, null];
    }

    private function client(GithubConnection $connection): PendingRequest
    {
        return Http::withToken($this->accessToken($connection))->acceptJson()->timeout(20);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paginate(GithubConnection $connection, string $url): array
    {
        $items = [];
        $pages = 0;

        while ($url !== '' && $pages < 50) {
            $response = $this->client($connection)->get($url)->throw();
            $items = array_merge($items, (array) $response->json('values', []));
            $url = (string) $response->json('next', '');
            $pages++;
        }

        return $items;
    }
}
