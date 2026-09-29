<?php

namespace App\Http\Controllers;

use App\Models\GithubConnection;
use App\Services\SourceProviders\SourceProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Connected Git accounts (GitHub, GitLab, Bitbucket). GitHub keeps its original routes;
 * the other providers use the {provider} routes.
 */
class GitHubConnectionController extends Controller
{
    public function __construct(private SourceProviderRegistry $providers) {}

    public function show(): View
    {
        $connections = Auth::user()->githubConnections()->withCount('projects')->get();

        return view('settings.github.show', [
            'connections' => $connections,
            'providers' => $this->providers->all(),
        ]);
    }

    public function redirect(): SymfonyRedirectResponse
    {
        return $this->providers->get('github')->redirectToProvider();
    }

    public function callback(): RedirectResponse
    {
        return $this->storeConnection('github');
    }

    public function redirectToProvider(string $provider): SymfonyRedirectResponse
    {
        return $this->providers->get($provider)->redirectToProvider();
    }

    public function providerCallback(string $provider): RedirectResponse
    {
        return $this->storeConnection($provider);
    }

    /**
     * Disconnecting deletes the row, and with it the encrypted tokens.
     */
    public function destroy(int $connection): RedirectResponse
    {
        Auth::user()->githubConnections()->findOrFail($connection)->delete();

        return redirect()
            ->route('settings.github.show')
            ->with('status', 'github-disconnected');
    }

    private function storeConnection(string $provider): RedirectResponse
    {
        $account = $this->providers->get($provider)->accountFromCallback();

        // One row per provider account; re-authorizing an account refreshes its token.
        GithubConnection::updateOrCreate(
            ['user_id' => Auth::id(), 'provider' => $provider, 'github_id' => $account->id],
            [
                'github_login' => $account->login,
                'access_token' => $account->accessToken,
                'refresh_token' => $account->refreshToken,
                'token_expires_at' => $account->expiresAt,
                'token_scope' => $account->scopes !== [] ? implode(',', $account->scopes) : null,
                'connected_at' => now(),
            ]
        );

        return redirect()
            ->route('settings.github.show')
            ->with('status', 'github-connected');
    }
}
