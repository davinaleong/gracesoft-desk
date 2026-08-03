<?php

use App\Contracts\CommitSummarizer;
use App\Jobs\SummarizeCommit;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Services\NullCommitSummarizer;
use App\Services\OpenAiCommitSummarizer;
use App\Support\SummaryResult;
use Illuminate\Support\Facades\Queue;

// ── Interface contract ────────────────────────────────────────────────────────

test('commitSummarizer interface contract is satisfied by OpenAiCommitSummarizer', function () {
    expect(OpenAiCommitSummarizer::class)
        ->toImplement(CommitSummarizer::class);
});
test('commitSummarizer interface contract is satisfied by NullCommitSummarizer', function () {
    expect(NullCommitSummarizer::class)
        ->toImplement(CommitSummarizer::class);
});
// ── Job: AI fallback trigger conditions ───────────────────────────────────────

test('summarize job skips when keyword already matches', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['fix'], 'is_default' => false]);

    $project = Project::factory()->create();
    $commit = CommitTimeEntry::factory()->create([
        'project_id' => $project->id,
        'message' => 'Fix auth bug',
        'status' => 'pending',
    ]);

    $summarizer = Mockery::mock(CommitSummarizer::class);
    $summarizer->shouldNotReceive('summarize');

    $job = new SummarizeCommit($commit);
    $job->handle($summarizer);

    $commit->refresh();
    expect($commit->ai_summary)->toBeNull()
        ->and($commit->ai_suggested_stage_id)->toBeNull();
});

test('summarize job calls provider when no keyword match', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['fix'], 'is_default' => false]);
    $testing = ProjectStage::create(['name' => 'Testing', 'sort_order' => 2, 'status' => 'active', 'keywords' => ['test'], 'is_default' => false]);

    $project = Project::factory()->create();
    $commit = CommitTimeEntry::factory()->create([
        'project_id' => $project->id,
        'message' => 'Initial project setup',
        'additions' => 40,
        'deletions' => 5,
        'changed_files' => 8,
        'status' => 'pending',
    ]);

    $summarizer = Mockery::mock(CommitSummarizer::class);
    $summarizer->shouldReceive('summarize')
        ->once()
        ->withArgs(function (array $commits, array $stageNames): bool {
            return $commits[0]['message'] === 'Initial project setup'
                && in_array('Testing', $stageNames, true);
        })
        ->andReturn(new SummaryResult(
            summary: 'Set up initial project scaffolding.',
            suggestedStageName: 'Testing',
        ));

    $job = new SummarizeCommit($commit);
    $job->handle($summarizer);

    $commit->refresh();
    expect($commit->ai_summary)->toBe('Set up initial project scaffolding.')
        ->and($commit->ai_suggested_stage_id)->toBe($testing->id);
});

test('summarize job stores null stage when provider suggests unknown stage name', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => [], 'is_default' => false]);

    $project = Project::factory()->create();
    $commit = CommitTimeEntry::factory()->create([
        'project_id' => $project->id,
        'message' => 'Miscellaneous updates',
        'status' => 'pending',
    ]);

    $summarizer = Mockery::mock(CommitSummarizer::class);
    $summarizer->shouldReceive('summarize')
        ->once()
        ->andReturn(new SummaryResult(summary: 'Miscellaneous work.', suggestedStageName: 'Nonexistent Stage'));

    $job = new SummarizeCommit($commit);
    $job->handle($summarizer);

    $commit->refresh();
    expect($commit->ai_suggested_stage_id)->toBeNull()
        ->and($commit->ai_summary)->toBe('Miscellaneous work.');
});

test('summarize job is dispatched for new commits via webhook', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_webhook_id' => 1,
        'github_webhook_secret' => 'webhook-secret',
    ]);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => 'newcommit00000000000000000000000000000001',
            'message' => 'New work',
            'author' => ['name' => 'Alice', 'email' => 'a@example.com'],
            'timestamp' => now()->toIso8601String(),
            'added' => [],
            'removed' => [],
            'modified' => ['app/Models/User.php'],
        ]],
    ];

    $body = json_encode($payload);
    $this->call('POST', "/webhooks/github/{$project->uuid}", [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'HTTP_X_GITHUB_EVENT' => 'push',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent();

    Queue::assertPushed(SummarizeCommit::class);
});

test('summarize job is not dispatched for duplicate commits', function () {
    Queue::fake();

    $project = Project::factory()->create([
        'github_repo' => 'octocat/hello-world',
        'github_webhook_id' => 1,
        'github_webhook_secret' => 'webhook-secret',
    ]);

    $sha = 'dupcommit0000000000000000000000000000000001';
    CommitTimeEntry::factory()->create(['project_id' => $project->id, 'sha' => $sha]);

    $payload = [
        'ref' => 'refs/heads/main',
        'commits' => [[
            'id' => $sha,
            'message' => 'Already ingested',
            'author' => ['name' => 'Bob', 'email' => 'b@example.com'],
            'timestamp' => now()->toIso8601String(),
            'added' => [],
            'removed' => [],
            'modified' => [],
        ]],
    ];

    $body = json_encode($payload);
    $this->call('POST', "/webhooks/github/{$project->uuid}", [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
        'HTTP_X_GITHUB_EVENT' => 'push',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertNoContent();

    Queue::assertNotPushed(SummarizeCommit::class);
});
