<?php

use App\Models\GithubConnection;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

function readyUserForGitHub(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('github settings page is accessible', function () {
    $user = readyUserForGitHub();

    $this->actingAs($user)
        ->get(route('settings.github.show'))
        ->assertOk()
        ->assertSee('GitHub Connection');
});

test('github settings page shows not-connected state when no connection exists', function () {
    $user = readyUserForGitHub();

    $this->actingAs($user)
        ->get(route('settings.github.show'))
        ->assertOk()
        ->assertSee('Not connected')
        ->assertSee('Connect GitHub');
});

test('github settings page shows connected state with login name', function () {
    $user = readyUserForGitHub();

    GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '12345',
        'github_login' => 'octocat',
        'access_token' => 'test-token',
        'token_scope' => 'repo,read:user',
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.github.show'))
        ->assertOk()
        ->assertSee('octocat')
        ->assertSee('Disconnect GitHub');
});

test('oauth callback stores github connection', function () {
    $user = readyUserForGitHub();

    $socialiteUser = (new SocialiteUser)->map([
        'id' => '99999',
        'nickname' => 'testuser',
        'name' => 'Test User',
        'email' => 'test@github.com',
        'token' => 'oauth-token-abc',
        'approvedScopes' => ['repo', 'read:user'],
    ]);
    $socialiteUser->token = 'oauth-token-abc';
    $socialiteUser->approvedScopes = ['repo', 'read:user'];

    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $this->actingAs($user)
        ->get(route('settings.github.callback'))
        ->assertRedirect(route('settings.github.show'));

    $this->assertDatabaseHas('github_connections', [
        'user_id' => $user->id,
        'github_login' => 'testuser',
        'github_id' => '99999',
    ]);
});

test('oauth callback updates existing connection', function () {
    $user = readyUserForGitHub();

    GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '99999',
        'github_login' => 'oldlogin',
        'access_token' => 'old-token',
        'token_scope' => 'repo',
        'connected_at' => now()->subDay(),
    ]);

    $socialiteUser = (new SocialiteUser)->map([
        'id' => '99999',
        'nickname' => 'newlogin',
        'name' => 'Test User',
        'email' => 'test@github.com',
        'token' => 'new-token',
        'approvedScopes' => [],
    ]);
    $socialiteUser->token = 'new-token';
    $socialiteUser->approvedScopes = [];

    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $this->actingAs($user)
        ->get(route('settings.github.callback'))
        ->assertRedirect(route('settings.github.show'));

    $this->assertDatabaseCount('github_connections', 1);
    $this->assertDatabaseHas('github_connections', ['github_login' => 'newlogin']);
});

test('disconnect deletes github connection', function () {
    $user = readyUserForGitHub();

    GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '12345',
        'github_login' => 'octocat',
        'access_token' => 'token',
        'token_scope' => 'repo',
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('settings.github.destroy'))
        ->assertRedirect(route('settings.github.show'));

    $this->assertDatabaseEmpty('github_connections');
});

test('github settings requires authentication', function () {
    $this->get(route('settings.github.show'))->assertRedirect(route('login'));
});
