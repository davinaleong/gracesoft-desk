<?php

namespace App\Services\SourceProviders;

use App\Models\GithubConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Providers whose access tokens expire (GitLab, Bitbucket) refresh them just before use.
 */
trait RefreshesOAuthTokens
{
    abstract protected function tokenUrl(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function refreshParameters(GithubConnection $connection): array;

    protected function refreshRequest(): PendingRequest
    {
        return Http::asForm()->acceptJson()->timeout(20);
    }

    public function accessToken(GithubConnection $connection): string
    {
        $expiresAt = $connection->token_expires_at;

        if ($expiresAt === null || $expiresAt->isAfter(now()->addMinute()) || blank($connection->refresh_token)) {
            return (string) $connection->access_token;
        }

        $response = $this->refreshRequest()->post($this->tokenUrl(), $this->refreshParameters($connection))->throw();

        $connection->forceFill([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $connection->refresh_token,
            'token_expires_at' => $response->json('expires_in') ? now()->addSeconds((int) $response->json('expires_in')) : null,
        ])->save();

        return (string) $connection->access_token;
    }
}
