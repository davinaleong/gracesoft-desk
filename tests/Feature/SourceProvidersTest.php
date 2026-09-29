<?php

use App\Jobs\IngestLargePushBatch;
use App\Jobs\SummarizeCommit;
use App\Models\CommitTimeEntry;
use App\Models\GithubConnection;
use App\Models\Project;
use App\Models\User;
use App\Services\SourceProviders\SourceProviderRegistry;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GitlabProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

function readyUserForSources(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function pushFixture(string $provider): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/webhooks/{$provider}-push.json")), true);
}

function linkedProject(string $provider, array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'source_provider' => $provider,
        'github_repo' => $provider === 'gitlab' ? 'gracesoft/apps/desk-demo' : 'gracesoft/desk-demo',
        'github_branch' => 'main',
        'github_webhook_secret' => 'hook-secret',
        'source_webhook_ref' => '77',
    ], $overrides));
}

/**
 * Builds the signed server headers each provider sends for a body.
 *
 * @return array<string, string>
 */
function providerHeaders(string $provider, string $body, string $secret = 'hook-secret', bool $valid = true): array
{
    $signature = 'sha256='.hash_hmac('sha256', $body, $valid ? $secret : 'wrong-secret');

    return match ($provider) {
        'github' => ['HTTP_X_GITHUB_EVENT' => 'push', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
        'gitlab' => ['HTTP_X_GITLAB_EVENT' => 'Push Hook', 'HTTP_X_GITLAB_TOKEN' => $valid ? $secret : 'wrong-secret'],
        'bitbucket' => ['HTTP_X_EVENT_KEY' => 'repo:push', 'HTTP_X_HUB_SIGNATURE' => $signature],
    } + ['CONTENT_TYPE' => 'application/json'];
}

function sendPush(string $provider, Project $project, array $payload, array $headers): TestResponse
{
    return test()->call('POST', "/webhooks/{$provider}/{$project->uuid}", [], [], [], $headers, json_encode($payload));
}

dataset('providers', ['github', 'gitlab', 'bitbucket']);

beforeEach(function (): void {
    Queue::fake([SummarizeCommit::class]);
});

test('a valid request is accepted and stores the pushed commits', function (string $provider) {
    $project = linkedProject($provider);
    $payload = pushFixture($provider);

    sendPush($provider, $project, $payload, providerHeaders($provider, json_encode($payload)))->assertNoContent();

    $commits = CommitTimeEntry::query()->where('project_id', $project->id)->orderBy('committed_at')->get();

    expect($commits)->toHaveCount(2)
        ->and($commits->pluck('provider')->unique()->all())->toBe([$provider])
        ->and($commits->pluck('repo')->unique()->all())->toBe([$project->github_repo])
        ->and($commits->first()->message)->toBe('feat: add invoice PDF export');
})->with('providers');

test('an invalid or missing signature is rejected with no commits stored', function (string $provider) {
    $project = linkedProject($provider);
    $payload = pushFixture($provider);
    $body = json_encode($payload);

    sendPush($provider, $project, $payload, providerHeaders($provider, $body, valid: false))->assertForbidden();

    $unsigned = array_filter(providerHeaders($provider, $body), fn ($key) => ! str_contains($key, 'SIGNATURE') && ! str_contains($key, 'TOKEN'), ARRAY_FILTER_USE_KEY);
    sendPush($provider, $project, $payload, $unsigned)->assertForbidden();

    expect(CommitTimeEntry::query()->count())->toBe(0);
})->with('providers');

test('a project only accepts pushes on its own provider route', function () {
    $project = linkedProject('gitlab');
    $payload = pushFixture('bitbucket');

    sendPush('bitbucket', $project, $payload, providerHeaders('bitbucket', json_encode($payload)))->assertNotFound();

    expect(CommitTimeEntry::query()->count())->toBe(0);
});

test('recorded push payloads from each provider normalise to the same commit shape', function () {
    $registry = app(SourceProviderRegistry::class);
    $pushes = collect(['github', 'gitlab', 'bitbucket'])->mapWithKeys(fn ($provider) => [$provider => $registry->get($provider)->parsePush(pushFixture($provider))]);

    $expectedKeys = ['sha', 'author_name', 'author_email', 'timestamp', 'message', 'branch', 'files_changed', 'file_paths'];

    foreach ($pushes as $provider => $push) {
        expect($push->branch)->toBe('main', $provider)
            ->and($push->commits)->toHaveCount(2);

        foreach ($push->commits as $commit) {
            expect(array_keys($commit))->toBe($expectedKeys);
        }

        // Oldest first, same SHAs, authors, messages and instants everywhere.
        expect(array_column($push->commits, 'sha'))->toBe(['0d1a26e67d8f5eaf1f6ba5c57fc3c7d91ac0fd1c', 'a10867b14bb761a232cd80139fbd4c0d33264240'])
            ->and(array_column($push->commits, 'author_name'))->toBe(['Dana Dev', 'Dana Dev'])
            ->and(array_column($push->commits, 'author_email'))->toBe(['dana@gracesoft.example', 'dana@gracesoft.example'])
            ->and(array_map('trim', array_column($push->commits, 'message')))->toBe(['feat: add invoice PDF export', 'fix: round GST per line'])
            ->and(array_map(fn ($t) => Carbon::parse($t)->utc()->toIso8601String(), array_column($push->commits, 'timestamp')))
            ->toBe(['2026-09-27T15:30:00+00:00', '2026-09-28T01:05:12+00:00']);
    }

    expect($pushes['github']->commits[0]['files_changed'])->toBe(3)
        ->and($pushes['gitlab']->commits[0]['file_paths'])->toBe($pushes['github']->commits[0]['file_paths'])
        ->and($pushes['bitbucket']->commits[0]['files_changed'])->toBeNull();
});

test('pushes to untracked branches are ignored for every provider', function (string $provider) {
    $project = linkedProject($provider, ['github_branch' => 'release']);
    $payload = pushFixture($provider);

    sendPush($provider, $project, $payload, providerHeaders($provider, json_encode($payload)))->assertNoContent();

    expect(CommitTimeEntry::query()->count())->toBe(0);
})->with('providers');

test('deduplication keys on provider, repo and sha', function () {
    $github = linkedProject('github');
    $gitlab = linkedProject('gitlab');
    $payload = pushFixture('github');
    $gitlabPayload = pushFixture('gitlab');

    // Same push delivered twice, plus the same SHAs arriving from a different provider/repo.
    sendPush('github', $github, $payload, providerHeaders('github', json_encode($payload)))->assertNoContent();
    sendPush('github', $github, $payload, providerHeaders('github', json_encode($payload)))->assertNoContent();
    sendPush('gitlab', $gitlab, $gitlabPayload, providerHeaders('gitlab', json_encode($gitlabPayload)))->assertNoContent();

    expect(CommitTimeEntry::query()->where('provider', 'github')->count())->toBe(2)
        ->and(CommitTimeEntry::query()->where('provider', 'gitlab')->count())->toBe(2)
        ->and(CommitTimeEntry::query()->count())->toBe(4);
});

test('the large-push threshold and queued ingest work for every provider', function (string $provider) {
    Queue::fake();
    $project = linkedProject($provider);
    $payload = pushFixture($provider);

    // Blow the push up past the cold-start threshold of 10 commits.
    if ($provider === 'bitbucket') {
        $template = $payload['push']['changes'][0]['commits'][0];
        $payload['push']['changes'][0]['commits'] = array_map(fn ($i) => ['hash' => sha1("c{$i}")] + $template, range(1, 12));
    } else {
        $template = $payload['commits'][0];
        $payload['commits'] = array_map(fn ($i) => ['id' => sha1("c{$i}")] + $template, range(1, 12));
    }

    sendPush($provider, $project, $payload, providerHeaders($provider, json_encode($payload)))->assertNoContent();

    Queue::assertPushed(IngestLargePushBatch::class, fn (IngestLargePushBatch $job) => $job->project->is($project) && count($job->commits) === 12);
    expect(CommitTimeEntry::query()->count())->toBe(0);
})->with('providers');

test('linking registers the webhook on the provider and unlinking removes it', function (string $provider, string $hooksUrl, array $created, string $deleteUrl) {
    $user = readyUserForSources();
    $connection = GithubConnection::create([
        'user_id' => $user->id,
        'provider' => $provider,
        'github_id' => '42',
        'github_login' => 'dana',
        'access_token' => 'provider-token',
        'connected_at' => now(),
    ]);
    $project = Project::factory()->create();
    $repo = $provider === 'gitlab' ? 'gracesoft/apps/desk-demo' : 'gracesoft/desk-demo';

    Http::fake([
        $hooksUrl => Http::response($created, 201),
        $deleteUrl => Http::response(null, 204),
    ]);

    $this->actingAs($user)->post(route('projects.github.store', $project), [
        'github_connection_id' => $connection->id,
        'github_repo' => $repo,
        'github_branch' => 'main',
    ])->assertRedirect(route('projects.show', $project));

    $project->refresh();

    expect($project->source_provider)->toBe($provider)
        ->and($project->github_repo)->toBe($repo)
        ->and($project->source_webhook_ref)->toBe((string) ($created['id'] ?? $created['uuid']));

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
        && $request['url'] === route('webhooks.receive', [$provider, $project]));

    $this->actingAs($user)->delete(route('projects.github.destroy', $project))->assertRedirect(route('projects.show', $project));

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE' && $request->url() === str_replace('*', '', $deleteUrl));
    expect($project->fresh()->github_repo)->toBeNull()
        ->and($project->fresh()->source_provider)->toBeNull();
})->with([
    'gitlab' => ['gitlab', 'https://gitlab.com/api/v4/projects/gracesoft%2Fapps%2Fdesk-demo/hooks', ['id' => 991], 'https://gitlab.com/api/v4/projects/gracesoft%2Fapps%2Fdesk-demo/hooks/991'],
    'bitbucket' => ['bitbucket', 'https://api.bitbucket.org/2.0/repositories/gracesoft/desk-demo/hooks', ['uuid' => '{b5c3-hook}'], 'https://api.bitbucket.org/2.0/repositories/gracesoft/desk-demo/hooks/%7Bb5c3-hook%7D'],
]);

test('connecting a gitlab account stores encrypted tokens, and disconnecting deletes them', function () {
    $user = readyUserForSources();

    $socialiteUser = (new SocialiteUser)->map(['id' => '555', 'nickname' => 'dana-gl', 'name' => 'Dana']);
    $socialiteUser->token = 'gl-access-token';
    $socialiteUser->refreshToken = 'gl-refresh-token';
    $socialiteUser->expiresIn = 7200;
    $socialiteUser->approvedScopes = ['api', 'read_user'];

    $driver = Mockery::mock(GitlabProvider::class);
    $driver->shouldReceive('user')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('gitlab')->andReturn($driver);

    $this->actingAs($user)->get(route('settings.git.callback', 'gitlab'))->assertRedirect(route('settings.github.show'));

    $row = DB::table('github_connections')->where('provider', 'gitlab')->first();

    expect($row->github_login)->toBe('dana-gl')
        ->and($row->access_token)->not->toContain('gl-access-token')
        ->and($row->refresh_token)->not->toContain('gl-refresh-token')
        ->and(GithubConnection::query()->find($row->id)->refresh_token)->toBe('gl-refresh-token');

    $this->actingAs($user)->delete(route('settings.github.destroy', $row->id))->assertRedirect(route('settings.github.show'));

    expect(DB::table('github_connections')->count())->toBe(0);
});

test('expired gitlab tokens are refreshed before use', function () {
    $user = readyUserForSources();
    config(['services.gitlab.client_id' => 'cid', 'services.gitlab.client_secret' => 'csecret']);
    $connection = GithubConnection::create([
        'user_id' => $user->id,
        'provider' => 'gitlab',
        'github_id' => '555',
        'github_login' => 'dana-gl',
        'access_token' => 'stale-token',
        'refresh_token' => 'refresh-me',
        'token_expires_at' => now()->subMinute(),
        'connected_at' => now(),
    ]);

    Http::fake([
        'https://gitlab.com/oauth/token' => Http::response(['access_token' => 'fresh-token', 'refresh_token' => 'refresh-2', 'expires_in' => 7200]),
        'https://gitlab.com/api/v4/projects*' => Http::response([['path_with_namespace' => 'gracesoft/app', 'visibility' => 'private', 'default_branch' => 'main']]),
    ]);

    $this->actingAs($user)->getJson(route('settings.github.repos', $connection->id))
        ->assertOk()
        ->assertJsonPath('0.full_name', 'gracesoft/app');

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/api/v4/projects') && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    expect($connection->fresh()->access_token)->toBe('fresh-token')
        ->and($connection->fresh()->refresh_token)->toBe('refresh-2');
});

test('the settings page offers every provider', function () {
    config(['services.gitlab.client_id' => 'cid']);
    $user = readyUserForSources();

    $this->actingAs($user)->get(route('settings.github.show'))
        ->assertOk()
        ->assertSee('Connect GitLab')
        ->assertSee('Bitbucket: add its OAuth client ID');
});

test('prelaunch check warns when a provider in use has no oauth credentials', function () {
    config(['services.gitlab.client_id' => null]);
    linkedProject('gitlab');

    $this->artisan('desk:prelaunch-check')->expectsOutputToContain('Git providers in use have OAuth credentials');
});
