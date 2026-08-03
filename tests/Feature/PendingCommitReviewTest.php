<?php

use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\CommitStageMatcherService;

function m5User(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

// ── CommitStageMatcherService unit tests ──────────────────────────────────────

test('keyword matcher returns matching stage by sort_order', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 2, 'status' => 'active', 'keywords' => ['fix', 'implement', 'feature'], 'is_default' => false]);
    ProjectStage::create(['name' => 'Testing', 'sort_order' => 3, 'status' => 'active', 'keywords' => ['test', 'spec', 'qa'], 'is_default' => false]);

    $matcher = new CommitStageMatcherService;

    expect($matcher->match('Fix login bug')->name)->toBe('Development')
        ->and($matcher->match('Add test coverage')->name)->toBe('Testing');
});

test('keyword matcher returns null when no keywords match', function () {
    ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['fix', 'implement'], 'is_default' => false]);

    $matcher = new CommitStageMatcherService;

    expect($matcher->match('Initial commit'))->toBeNull();
});

test('keyword matcher checks branch name as well as message', function () {
    ProjectStage::create(['name' => 'Testing', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['test'], 'is_default' => false]);

    $matcher = new CommitStageMatcherService;

    expect($matcher->match('Update readme', 'feature/test-improvements'))->not->toBeNull()
        ->and($matcher->match('Update readme', 'feature/test-improvements')->name)->toBe('Testing');
});

test('keyword matching is case insensitive', function () {
    ProjectStage::create(['name' => 'Deployment', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['deploy'], 'is_default' => false]);

    $matcher = new CommitStageMatcherService;

    expect($matcher->match('DEPLOY to production'))->not->toBeNull();
});

test('snap duration rounds to nearest 15 minutes', function () {
    expect(CommitStageMatcherService::snapDuration(7))->toBe(15)
        ->and(CommitStageMatcherService::snapDuration(20))->toBe(15)
        ->and(CommitStageMatcherService::snapDuration(23))->toBe(30)
        ->and(CommitStageMatcherService::snapDuration(60))->toBe(60)
        ->and(CommitStageMatcherService::snapDuration(1))->toBe(15); // minimum 15
});

// ── Review screen ─────────────────────────────────────────────────────────────

test('pending commits index shows commits for the project', function () {
    $user = m5User();
    $project = Project::factory()->create();
    CommitTimeEntry::factory()->count(3)->create(['project_id' => $project->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->get(route('projects.pending-commits.index', $project))
        ->assertOk()
        ->assertSee('Pending Commits');
});

test('pending commits index does not show approved commits', function () {
    $user = m5User();
    $project = Project::factory()->create();
    $approved = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'approved', 'message' => 'Already done']);

    $this->actingAs($user)
        ->get(route('projects.pending-commits.index', $project))
        ->assertOk()
        ->assertDontSee('Already done');
});

test('pending commits index requires authentication', function () {
    $project = Project::factory()->create();

    $this->get(route('projects.pending-commits.index', $project))->assertRedirect(route('login'));
});

// ── Conversion form ───────────────────────────────────────────────────────────

test('convert page shows suggested stage based on keywords', function () {
    $user = m5User();
    $project = Project::factory()->create();
    ProjectStage::create(['name' => 'Testing', 'sort_order' => 1, 'status' => 'active', 'keywords' => ['test'], 'is_default' => false]);
    $commit = CommitTimeEntry::factory()->create([
        'project_id' => $project->id,
        'message' => 'Add test for login',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('projects.pending-commits.create', [$project, $commit]))
        ->assertOk()
        ->assertSee('Testing');
});

// ── Convert to time entry ─────────────────────────────────────────────────────

test('converting a commit creates a time entry and marks it approved', function () {
    $user = m5User();
    $project = Project::factory()->create(['is_billable' => true]);
    $stage = ProjectStage::create(['name' => 'Development', 'sort_order' => 1, 'status' => 'active', 'keywords' => [], 'is_default' => false]);
    $commit = CommitTimeEntry::factory()->create([
        'project_id' => $project->id,
        'message' => 'Add feature',
        'status' => 'pending',
        'committed_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.store', [$project, $commit]), [
            'project_stage_uuid' => $stage->uuid,
            'entry_date' => now()->toDateString(),
            'duration_minutes' => 30,
            'is_billable' => '1',
            'notes' => 'Add feature',
        ])
        ->assertRedirect(route('projects.pending-commits.index', $project))
        ->assertSessionHas('status', 'commit-converted');

    $commit->refresh();
    expect($commit->status)->toBe('approved')
        ->and($commit->converted_time_entry_id)->not->toBeNull();

    $this->assertDatabaseHas('time_entries', [
        'project_id' => $project->id,
        'duration_minutes' => 30,
        'notes' => 'Add feature',
    ]);
});

test('duration is snapped to nearest 15 minutes on store', function () {
    $user = m5User();
    $project = Project::factory()->create();
    $commit = CommitTimeEntry::factory()->create(['project_id' => $project->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->post(route('projects.pending-commits.store', [$project, $commit]), [
            'project_stage_uuid' => null,
            'entry_date' => now()->toDateString(),
            'duration_minutes' => 22, // should snap to 15
            'is_billable' => '0',
            'notes' => 'Test',
        ]);

    $this->assertDatabaseHas('time_entries', ['duration_minutes' => 15]);
});

test('commit from a different project returns 404', function () {
    $user = m5User();
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $commit = CommitTimeEntry::factory()->create(['project_id' => $projectB->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->get(route('projects.pending-commits.create', [$projectA, $commit]))
        ->assertNotFound();
});
