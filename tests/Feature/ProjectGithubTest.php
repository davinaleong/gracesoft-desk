<?php

use App\Models\GithubConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function githubReadyUser(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function withGitHubConnection(User $user, string $token = 'fake-token'): GithubConnection
{
    return GithubConnection::create([
        'user_id' => $user->id,
        'github_id' => '12345',
        'github_login' => 'octocat',
        'access_token' => $token,
        'token_scope' => 'repo,read:user',
        'connected_at' => now(),
    ]);
}

// ── Repo picker (JSON) ────────────────────────────────────────────────────────

test('repos endpoint returns json list when connected', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);

    Http::fake([
        'https://api.github.com/user/repos*' => Http::response([
            ['full_name' => 'octocat/hello-world', 'private' => false, 'default_branch' => 'main'],
            ['full_name' => 'octocat/Spoon-Knife', 'private' => false, 'default_branch' => 'main'],
        ], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('settings.github.repos'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['full_name' => 'octocat/hello-world', 'default_branch' => 'main']);
});

test('repos endpoint returns 403 when no github connection', function () {
    $user = githubReadyUser();

    $this->actingAs($user)
        ->getJson(route('settings.github.repos'))
        ->assertForbidden();
});

test('repos endpoint requires authentication', function () {
    $this->getJson(route('settings.github.repos'))->assertUnauthorized();
});

// ── Branch picker (JSON) ──────────────────────────────────────────────────────

test('branches endpoint returns json list when connected', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);

    Http::fake([
        'https://api.github.com/repos/octocat/hello-world/branches*' => Http::response([
            ['name' => 'main'],
            ['name' => 'develop'],
        ], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('settings.github.branches', ['repo' => 'octocat/hello-world']))
        ->assertOk()
        ->assertJson(['main', 'develop']);
});

test('branches endpoint returns 403 when no github connection', function () {
    $user = githubReadyUser();

    $this->actingAs($user)
        ->getJson(route('settings.github.branches', ['repo' => 'octocat/hello-world']))
        ->assertForbidden();
});

test('branches endpoint rejects an invalid repo format', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);

    $this->actingAs($user)
        ->getJson(route('settings.github.branches', ['repo' => 'not-a-valid-repo']))
        ->assertUnprocessable();
});

test('branches endpoint requires authentication', function () {
    $this->getJson(route('settings.github.branches', ['repo' => 'octocat/hello-world']))->assertUnauthorized();
});

// ── Link repo ────────────────────────────────────────────────────────────────

test('linking a repo and branch registers a webhook and persists fields', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create();

    Http::fake([
        'https://api.github.com/repos/octocat/hello-world/hooks' => Http::response(['id' => 9876], 201),
    ]);

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/hello-world', 'github_branch' => 'main'])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('status', 'github-repo-linked');

    $project->refresh();
    expect($project->github_repo)->toBe('octocat/hello-world')
        ->and($project->github_branch)->toBe('main')
        ->and($project->github_webhook_id)->toBe(9876)
        ->and($project->github_webhook_secret)->not->toBeNull();
});

test('linking a repo rejects an invalid repo format', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'not-a-valid-repo', 'github_branch' => 'main'])
        ->assertSessionHasErrors('github_repo');
});

test('linking a repo without a branch is rejected', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/hello-world'])
        ->assertSessionHasErrors('github_branch');

    $project->refresh();
    expect($project->github_repo)->toBeNull();
});

test('linking a repo requires github connection', function () {
    $user = githubReadyUser();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/hello-world', 'github_branch' => 'main'])
        ->assertForbidden();
});

test('linking a repo already linked to another project is rejected', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    Project::factory()->create(['github_repo' => 'octocat/hello-world']);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/hello-world', 'github_branch' => 'main'])
        ->assertSessionHasErrors('github_repo');

    $project->refresh();
    expect($project->github_repo)->toBeNull();
});

test('relinking a project to the same repo it already holds is allowed', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_branch' => 'main',
        'github_webhook_id' => 111,
        'github_webhook_secret' => 'old-secret',
    ]);

    Http::fake([
        'https://api.github.com/repos/octocat/hello-world/hooks/111' => Http::response(null, 204),
        'https://api.github.com/repos/octocat/hello-world/hooks' => Http::response(['id' => 222], 201),
    ]);

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/hello-world', 'github_branch' => 'develop'])
        ->assertSessionDoesntHaveErrors('github_repo')
        ->assertRedirect(route('projects.show', $project));

    $project->refresh();
    expect($project->github_repo)->toBe('octocat/hello-world')
        ->and($project->github_branch)->toBe('develop')
        ->and($project->github_webhook_id)->toBe(222);
});

test('relinking a repo removes the old webhook first', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create([
        'github_repo' => 'octocat/old-repo',
        'github_branch' => 'main',
        'github_webhook_id' => 111,
        'github_webhook_secret' => 'old-secret',
    ]);

    Http::fake([
        'https://api.github.com/repos/octocat/old-repo/hooks/111' => Http::response(null, 204),
        'https://api.github.com/repos/octocat/new-repo/hooks' => Http::response(['id' => 222], 201),
    ]);

    $this->actingAs($user)
        ->post(route('projects.github.store', $project), ['github_repo' => 'octocat/new-repo', 'github_branch' => 'main'])
        ->assertRedirect(route('projects.show', $project));

    Http::assertSent(fn ($req) => str_contains($req->url(), '/repos/octocat/old-repo/hooks/111'));

    $project->refresh();
    expect($project->github_repo)->toBe('octocat/new-repo')
        ->and($project->github_webhook_id)->toBe(222);
});

// ── Unlink repo ───────────────────────────────────────────────────────────────

test('unlinking a repo removes webhook and clears fields', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_branch' => 'main',
        'github_webhook_id' => 9876,
        'github_webhook_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://api.github.com/repos/octocat/hello-world/hooks/9876' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->delete(route('projects.github.destroy', $project))
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('status', 'github-repo-unlinked');

    $project->refresh();
    expect($project->github_repo)->toBeNull()
        ->and($project->github_branch)->toBeNull()
        ->and($project->github_webhook_id)->toBeNull()
        ->and($project->github_webhook_secret)->toBeNull();
});

test('unlinking still clears fields when webhook is already gone on github', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_branch' => 'main',
        'github_webhook_id' => 9876,
        'github_webhook_secret' => 'secret-value',
    ]);

    Http::fake([
        'https://api.github.com/repos/octocat/hello-world/hooks/9876' => Http::response(null, 404),
    ]);

    $this->actingAs($user)
        ->delete(route('projects.github.destroy', $project))
        ->assertRedirect(route('projects.show', $project));

    $project->refresh();
    expect($project->github_repo)->toBeNull();
});

test('project show page displays linked repository and branch', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_branch' => 'main',
        'github_webhook_id' => 9876,
        'github_webhook_secret' => 'secret-value',
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('octocat/hello-world')
        ->assertSee('main')
        ->assertSee('Unlink');
});

test('project show page prompts to link when connected but no repo', function () {
    $user = githubReadyUser();
    withGitHubConnection($user);
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Link Repository');
});

test('project show page prompts to connect github when not connected', function () {
    $user = githubReadyUser();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Connect');
});
