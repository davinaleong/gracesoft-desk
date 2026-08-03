<?php

use App\Contracts\CommitSummarizer;
use App\Jobs\SummarizeSquashedCommits;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\CommitStageMatcherService;
use App\Support\SummaryResult;
use Illuminate\Support\Facades\Queue;

function squashTestUser(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

// ── Squash grouping ─────────────────────────────────────────────────────────

test('squashing marks children with squashed status and squashed_into set', function () {
    Queue::fake();

    $user = squashTestUser();
    $project = Project::factory()->create();
    $anchor = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'committed_at' => now()->subMinutes(30), 'status' => 'pending']);
    $child = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'committed_at' => now(), 'status' => 'pending']);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.squash', $project), [
            'commit_uuids' => [$anchor->uuid, $child->uuid],
        ])
        ->assertRedirect(route('projects.pending-commits.create', [$project, $anchor]));

    $child->refresh();
    $anchor->refresh();

    expect($child->status)->toBe('squashed')
        ->and($child->squashed_into)->toBe($anchor->id)
        ->and($anchor->status)->toBe('pending');

    Queue::assertPushed(SummarizeSquashedCommits::class, fn ($job) => $job->anchor->is($anchor));
});

test('squash requires at least two pending commits', function () {
    $user = squashTestUser();
    $project = Project::factory()->create();
    $only = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.squash', $project), [
            'commit_uuids' => [$only->uuid],
        ])
        ->assertSessionHasErrors('commit_uuids');

    $only->refresh();
    expect($only->status)->toBe('pending');
});

test('squash ignores commits from another project', function () {
    $user = squashTestUser();
    $project = Project::factory()->create();
    $other = Project::factory()->create();

    $mine = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'pending']);
    $theirs = CommitTimeEntry::factory()->create(['project_id' => $other->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.squash', $project), [
            'commit_uuids' => [$mine->uuid, $theirs->uuid],
        ])
        ->assertSessionHasErrors('commit_uuids');

    $theirs->refresh();
    expect($theirs->status)->toBe('pending')->and($theirs->squashed_into)->toBeNull();
});

// ── Child-row state transitions on conversion ───────────────────────────────

test('converting the anchor of a squash group also approves its children', function () {
    $user = squashTestUser();
    $project = Project::factory()->create();
    $stage = ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => [], 'is_default' => false]);

    $anchor = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'pending']);
    $child = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'squashed', 'squashed_into' => $anchor->id]);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.store', [$project, $anchor]), [
            'project_stage_uuid' => $stage->uuid,
            'entry_date' => now()->toDateString(),
            'duration_minutes' => 40,
            'is_billable' => '1',
        ])
        ->assertRedirect(route('projects.pending-commits.index', $project));

    $timeEntry = TimeEntry::query()->latest('id')->first();
    $anchor->refresh();
    $child->refresh();

    expect($anchor->status)->toBe('approved')
        ->and($anchor->converted_time_entry_id)->toBe($timeEntry->id)
        ->and($child->status)->toBe('approved')
        ->and($child->converted_time_entry_id)->toBe($timeEntry->id);
});

// ── Duration handling on squash ─────────────────────────────────────────────

test('duration is snapped to 15-minute increments when converting a squashed group', function () {
    $user = squashTestUser();
    $project = Project::factory()->create();

    $anchor = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'pending']);
    CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'squashed', 'squashed_into' => $anchor->id]);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.store', [$project, $anchor]), [
            'entry_date' => now()->toDateString(),
            'duration_minutes' => 50,
            'is_billable' => '0',
        ]);

    $timeEntry = TimeEntry::query()->latest('id')->first();

    expect($timeEntry->duration_minutes)->toBe(CommitStageMatcherService::snapDuration(50));
});

// ── AI summary on squashed set ───────────────────────────────────────────────

test('summarize squashed commits job runs on the full group not per-commit', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => [], 'is_default' => false]);
    $testing = ProjectStage::create(['name' => 'Testing', 'sort_order' => 2, 'status' => 'active', 'keywords' => [], 'is_default' => false]);

    $project = Project::factory()->create();
    $anchor = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'message' => 'Add widget', 'status' => 'pending']);
    $child = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'message' => 'Add widget tests', 'status' => 'squashed', 'squashed_into' => $anchor->id]);

    $summarizer = Mockery::mock(CommitSummarizer::class);
    $summarizer->shouldReceive('summarize')
        ->once()
        ->withArgs(function (array $commits, array $stageNames) use ($anchor, $child): bool {
            $messages = array_column($commits, 'message');

            return count($commits) === 2
                && in_array($anchor->message, $messages, true)
                && in_array($child->message, $messages, true);
        })
        ->andReturn(new SummaryResult(summary: 'Added a widget with tests.', suggestedStageName: 'Testing'));

    $job = new SummarizeSquashedCommits($anchor);
    $job->handle($summarizer);

    $anchor->refresh();
    $child->refresh();

    expect($anchor->ai_summary)->toBe('Added a widget with tests.')
        ->and($anchor->ai_suggested_stage_id)->toBe($testing->id)
        ->and($child->ai_summary)->toBeNull();
});

test('summarize squashed commits job skips AI when a keyword already matches the group', function () {
    ProjectStage::create(['name' => 'Testing', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['test'], 'is_default' => false]);

    $project = Project::factory()->create();
    $anchor = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'message' => 'Refactor internals', 'status' => 'pending']);
    CommitTimeEntry::factory()->create(['project_id' => $project->id, 'message' => 'Add tests for internals', 'status' => 'squashed', 'squashed_into' => $anchor->id]);

    $summarizer = Mockery::mock(CommitSummarizer::class);
    $summarizer->shouldNotReceive('summarize');

    $job = new SummarizeSquashedCommits($anchor);
    $job->handle($summarizer);

    $anchor->refresh();
    expect($anchor->ai_summary)->toBeNull();
});
