<?php

use App\Models\GithubConnection;
use App\Models\Project;
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

    $connection = GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '12345',
        'github_login' => 'octocat',
        'access_token' => 'token',
        'token_scope' => 'repo',
        'connected_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('settings.github.destroy', $connection))
        ->assertRedirect(route('settings.github.show'));

    $this->assertDatabaseEmpty('github_connections');
});

test('oauth callback adds a second account instead of replacing the first', function () {
    $user = readyUserForGitHub();

    GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '111',
        'github_login' => 'first',
        'access_token' => 'token-1',
        'connected_at' => now(),
    ]);

    $socialiteUser = (new SocialiteUser)->map(['id' => '222', 'nickname' => 'second']);
    $socialiteUser->token = 'token-2';
    $socialiteUser->approvedScopes = ['repo'];

    $provider = Mockery::mock(GithubProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

    $this->actingAs($user)->get(route('settings.github.callback'))->assertRedirect(route('settings.github.show'));

    $this->assertDatabaseCount('github_connections', 2);
    $this->assertDatabaseHas('github_connections', ['github_login' => 'first']);
    $this->assertDatabaseHas('github_connections', ['github_login' => 'second']);
});

test('github settings page lists every connected account', function () {
    $user = readyUserForGitHub();

    foreach (['111' => 'first-acct', '222' => 'second-acct'] as $id => $login) {
        GithubConnection::create([
            'user_id' => $user->id,
            'github_id' => $id,
            'github_login' => $login,
            'access_token' => 't',
            'connected_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('settings.github.show'))
        ->assertOk()
        ->assertSee('first-acct')
        ->assertSee('second-acct')
        ->assertSee('Connect another GitHub account');
});

test('disconnecting an account keeps projects linked but detaches them from it', function () {
    $user = readyUserForGitHub();
    $connection = GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '111',
        'github_login' => 'first',
        'access_token' => 't',
        'connected_at' => now(),
    ]);
    $project = Project::factory()->create(['github_connection_id' => $connection->id, 'github_repo' => 'first/site']);

    $this->actingAs($user)->delete(route('settings.github.destroy', $connection));

    expect($project->refresh()->github_connection_id)->toBeNull()
        ->and($project->github_repo)->toBe('first/site');
});

test('github settings requires authentication', function () {
    $this->get(route('settings.github.show'))->assertRedirect(route('login'));
});
