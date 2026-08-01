<?php

namespace App\Http\Controllers;

use App\Models\GithubConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class GitHubConnectionController extends Controller
{
    public function show(): View
    {
        $connection = Auth::user()->githubConnection;

        return view('settings.github.show', ['connection' => $connection]);
    }

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        GithubConnection::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'github_id' => $githubUser->getId(),
                'github_login' => $githubUser->getNickname(),
                'access_token' => $githubUser->token,
                'token_scope' => $githubUser->approvedScopes
                    ? implode(',', $githubUser->approvedScopes)
                    : null,
                'connected_at' => now(),
            ]
        );

        return redirect()
            ->route('settings.github.show')
            ->with('status', 'github-connected');
    }

    public function destroy(): RedirectResponse
    {
        Auth::user()->githubConnection?->delete();

        return redirect()
            ->route('settings.github.show')
            ->with('status', 'github-disconnected');
    }
}
