<?php

use App\Models\Project;

function webhookProject(array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'github_repo' => 'octocat/hello-world',
        'github_webhook_id' => 1,
        'github_webhook_secret' => 'webhook-secret',
    ], $overrides));
}

/** @param array<string, mixed> $payload */
function signedWebhookPost(string $uuid, array $payload, string $secret = 'webhook-secret'): array
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

// ── Signature verification ─────────────────────────────────────────────────

test('webhook accepts a valid hmac signature', function () {
    $project = webhookProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => []];
    $data = signedWebhookPost($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();
});

test('webhook rejects a bad hmac signature', function () {
    $project = webhookProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => []];
    $data = signedWebhookPost($project->uuid, $payload, 'wrong-secret');

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertForbidden();
});

test('webhook rejects request with no signature header', function () {
    $project = webhookProject();
    $payload = ['ref' => 'refs/heads/main', 'commits' => []];
    $body = json_encode($payload);

    $this->call('POST', "/webhooks/github/{$project->uuid}", [], [], [], [
        'HTTP_X_GITHUB_EVENT' => 'push',
        'CONTENT_TYPE' => 'application/json',
    ], $body)
        ->assertForbidden();
});

test('webhook returns 404 for an unknown project uuid', function () {
    $this->post('/webhooks/github/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

// ── Push payload parsing ───────────────────────────────────────────────────

test('push payload creates pending commit rows', function () {
    $project = webhookProject();

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [
            [
                'id' => 'abc123def456abc123def456abc123def456abc1',
                'message' => 'Fix login bug',
                'author' => ['name' => 'Alice', 'email' => 'alice@example.com'],
                'timestamp' => '2026-08-03T10:00:00Z',
                'added' => ['app/Http/Controllers/AuthController.php'],
                'removed' => [],
                'modified' => ['resources/views/auth/login.blade.php'],
            ],
        ],
    ];

    $data = signedWebhookPost($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();

    $this->assertDatabaseHas('commit_time_entries', [
        'project_id' => $project->id,
        'sha' => 'abc123def456abc123def456abc123def456abc1',
        'branch' => 'main',
        'author_name' => 'Alice',
        'author_email' => 'alice@example.com',
        'message' => 'Fix login bug',
        'changed_files' => 2,
        'status' => 'pending',
    ]);
});

test('duplicate push does not create a second commit row', function () {
    $project = webhookProject();

    $commit = [
        'id' => 'dupe000000000000000000000000000000000001',
        'message' => 'Initial commit',
        'author' => ['name' => 'Bob', 'email' => 'bob@example.com'],
        'timestamp' => '2026-08-03T09:00:00Z',
        'added' => [],
        'removed' => [],
        'modified' => [],
    ];

    $payload = ['ref' => 'refs/heads/main', 'commits' => [$commit]];

    foreach (range(1, 2) as $_) {
        $data = signedWebhookPost($project->uuid, $payload);
        $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
            ->assertNoContent();
    }

    $this->assertDatabaseCount('commit_time_entries', 1);
});

test('non-push events are acknowledged but not ingested', function () {
    $project = webhookProject();
    $payload = ['action' => 'opened', 'issue' => ['title' => 'Bug report']];
    $data = signedWebhookPost($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], array_merge($data['server'], ['HTTP_X_GITHUB_EVENT' => 'issues']), $data['body'])
        ->assertNoContent();

    $this->assertDatabaseEmpty('commit_time_entries');
});

test('push payload with multiple commits creates multiple rows', function () {
    $project = webhookProject();

    $payload = [
        'ref' => 'refs/heads/feature/login',
        'commits' => [
            ['id' => 'sha1111111111111111111111111111111111111', 'message' => 'Add login', 'author' => ['name' => 'Alice', 'email' => 'a@example.com'], 'timestamp' => now()->toIso8601String(), 'added' => [], 'removed' => [], 'modified' => ['login.php']],
            ['id' => 'sha2222222222222222222222222222222222222', 'message' => 'Fix tests', 'author' => ['name' => 'Alice', 'email' => 'a@example.com'], 'timestamp' => now()->toIso8601String(), 'added' => [], 'removed' => [], 'modified' => ['tests/LoginTest.php']],
        ],
    ];

    $data = signedWebhookPost($project->uuid, $payload);

    $this->call('POST', $data['url'], [], [], [], $data['server'], $data['body'])
        ->assertNoContent();

    $this->assertDatabaseCount('commit_time_entries', 2);
    $this->assertDatabaseHas('commit_time_entries', ['branch' => 'feature/login']);
});
