<?php

namespace App\Contracts;

use App\Models\GithubConnection;
use App\Support\ProviderAccount;
use App\Support\PushEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * One Git host (GitHub, GitLab, Bitbucket…): OAuth, repos and branches, webhooks, and push parsing.
 */
interface SourceProvider
{
    /** Short key used in URLs and the database: github, gitlab, bitbucket. */
    public function key(): string;

    public function label(): string;

    /** Whether OAuth credentials are configured for this provider. */
    public function isConfigured(): bool;

    public function redirectToProvider(): RedirectResponse;

    public function accountFromCallback(): ProviderAccount;

    /**
     * A usable access token, refreshing it first if it has expired.
     */
    public function accessToken(GithubConnection $connection): string;

    /** @return array<int, array{full_name: string, private: bool, default_branch: string}> */
    public function listRepositories(GithubConnection $connection): array;

    /** @return array<int, string> */
    public function listBranches(GithubConnection $connection, string $repo): array;

    /** Registers a push webhook and returns the provider's webhook id. */
    public function registerWebhook(GithubConnection $connection, string $repo, string $url, string $secret): string;

    public function removeWebhook(GithubConnection $connection, string $repo, string $webhookId): void;

    /** Checks the request's signature or secret token against the project's webhook secret. */
    public function verifyRequest(Request $request, string $secret): bool;

    public function isPushEvent(Request $request): bool;

    /** @param  array<string, mixed>  $payload */
    public function parsePush(array $payload): PushEvent;

    /** Validation pattern for this provider's repository identifier. */
    public function repoPattern(): string;
}
