<?php

use App\Jobs\IngestLargePushBatch;
use App\Jobs\SummarizeCommit;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\PushSizeThresholdService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function batchTestUser(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function batchTestProject(array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'github_repo' => 'octocat/hello-world',
        'github_webhook_id' => 1,
        'github_webhook_secret' => 'webhook-secret',
    ], $overrides));
}

/** @param array<string, mixed> $payload */
function signedPush(string $uuid, array $payload, string $secret = 'webhook-secret'): array
{
    $body = json_encode($payload);

    return [
        'url' => "/webhooks/github/{$uuid}",
        'body' => $body,
        'server' => [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
            'HTTP_X_GITHUB_EVENT' => 'push',
            'CONTENT_TYPE' => 'application/json',
        ],
    ];
}

/** @return array<int, array<string, mixed>> */
function makeCommits(int $count): array
{
    return collect(range(1, $count))->map(fn (int $i): array => [
        'id' => str_pad((string) $i, 40, '0', STR_PAD_LEFT),
        'message' => "Commit {$i}",
        'author' => ['name' => 'Alice', 'email' => 'alice@example.com'],
        'timestamp' => now()->toIso8601String(),
        'added' => [],
        'removed' => [],
        'modified' => ["file{$i}.php"],
    ])->all();
}

function seedPushHistory(Project $project, array $counts): void
{
    foreach ($counts as $count) {
        $batchUuid = (string) Str::uuid();
        CommitTimeEntry::factory()->count($count)->create([
            'project_id' => $project->id,
            'push_batch_uuid' => $batchUuid,
            'from_large_batch' => false,
        ]);
    }
}

// ── Threshold calculation ───────────────────────────────────────────────────

test('threshold uses avg plus 2x stddev once enough push history exists', function () {
    $project = batchTestProject();

    // mean = 5, variance = 2, stddev = sqrt(2) ≈ 1.4142 → threshold ≈ 7.828
    seedPushHistory($project, [4, 6, 5, 7, 3]);

    $service = new PushSizeThresholdService;

    expect($service->threshold($project))->toBeGreaterThan(7.8)
        ->and($service->threshold($project))->toBeLessThan(7.9);

    expect($service->isLargePush($project, 8))->toBeTrue();
    expect($service->isLargePush($project, 7))->toBeFalse();
});

test('threshold ignores commits without a push batch uuid', function () {
    $project = batchTestProject();

    CommitTimeEntry::factory()->count(50)->create([
        'project_id' => $project->id,
        'push_batch_uuid' => null,
    ]);

    $service = new PushSizeThresholdService;

    // No usable push history → falls back to the cold-start constant.
    expect($service->threshold($project))->toBe(10.0);
});

// ── Cold-start fallback ──────────────────────────────────────────────────────

test('cold start with fewer than 3 prior pushes uses the fallback threshold', function () {
    $project = batchTestProject();

    seedPushHistory($project, [50]); // one huge outlier push, not enough history to trust stats

    $service = new PushSizeThresholdService;

    expect($service->threshold($project))->toBe(10.0);
});

test('a small push is processed synchronously without hitting the queue', function () {
    Queue::fake();

    $project = batchTestProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => makeCommits(3)];
    $data = signedPush($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();

    Queue::assertNotPushed(IngestLargePushBatch::class);
    $this->assertDatabaseCount('commit_time_entries', 3);
    $this->assertDatabaseHas('commit_time_entries', ['from_large_batch' => false]);
});

// ── Queue dispatch for large pushes ─────────────────────────────────────────

test('a push exceeding the threshold is dispatched to the queue instead of processed inline', function () {
    Queue::fake();

    $project = batchTestProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => makeCommits(11)];
    $data = signedPush($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();

    Queue::assertPushed(IngestLargePushBatch::class, fn (IngestLargePushBatch $job): bool => $job->project->is($project)
        && count($job->commits) === 11
    );

    // Nothing ingested yet — the queued job hasn't run.
    $this->assertDatabaseCount('commit_time_entries', 0);
});

test('processing a large push batch job marks rows from_large_batch and shares a push batch uuid', function () {
    // Partial fake: SummarizeCommit is intercepted, but IngestLargePushBatch
    // runs for real — the test QUEUE_CONNECTION is 'sync', so it executes
    // inline during dispatch() just like it would on a real sync queue.
    Queue::fake([SummarizeCommit::class]);

    $project = batchTestProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => makeCommits(11)];
    $data = signedPush($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();

    $this->assertDatabaseCount('commit_time_entries', 11);

    $rows = CommitTimeEntry::where('project_id', $project->id)->get();
    expect($rows->every(fn (CommitTimeEntry $c): bool => $c->from_large_batch === true))->toBeTrue();
    expect($rows->pluck('push_batch_uuid')->unique())->toHaveCount(1);

    // Large batches skip per-commit AI — they're reviewed as a squash group instead.
    Queue::assertNotPushed(SummarizeCommit::class);
});

test('a large push batch surfaces as a suggested squash group on the review screen', function () {
    Queue::fake([SummarizeCommit::class]);
    $user = batchTestUser();
    $project = batchTestProject();

    $payload = ['ref' => 'refs/heads/main', 'commits' => makeCommits(11)];
    $data = signedPush($project->uuid, $payload);
    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body']);

    $this->actingAs($user)
        ->get(route('projects.pending-commits.index', $project))
        ->assertOk()
        ->assertSee('commits landed in one large push', false);
});
