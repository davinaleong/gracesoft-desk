<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function makeBotTimeEntry(Project $project, ProjectStage $stage, User $user, array $overrides = []): TimeEntry
{
    return TimeEntry::factory()->create(array_merge([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
    ], $overrides));
}

test('bot time entries summary defaults to a rolling 30 day window', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $project = Project::factory()->create(['hourly_rate' => 100]);
    $stage = ProjectStage::query()->create(['name' => 'Development', 'sort_order' => 4, 'status' => 'active']);

    makeBotTimeEntry($project, $stage, $user, [
        'entry_date' => now()->subDays(5)->toDateString(),
        'duration_minutes' => 120,
        'is_billable' => true,
    ]);
    makeBotTimeEntry($project, $stage, $user, [
        'entry_date' => now()->subDays(10)->toDateString(),
        'duration_minutes' => 60,
        'is_billable' => false,
    ]);
    // Outside the rolling 30-day window — must not be counted.
    makeBotTimeEntry($project, $stage, $user, [
        'entry_date' => now()->subDays(45)->toDateString(),
        'duration_minutes' => 999,
        'is_billable' => true,
    ]);

    $this->getJson('/api/bot/time-entries/summary')
        ->assertOk()
        ->assertJson([
            'billable_hours' => 2.0,
            'non_billable_hours' => 1.0,
            'total_hours' => 3.0,
        ]);
});

test('bot time entries summary accepts a month override', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $project = Project::factory()->create(['hourly_rate' => 100]);
    $stage = ProjectStage::query()->create(['name' => 'Development', 'sort_order' => 4, 'status' => 'active']);

    makeBotTimeEntry($project, $stage, $user, [
        'entry_date' => '2026-03-15',
        'duration_minutes' => 180,
        'is_billable' => true,
    ]);
    // A different month — must not be counted.
    makeBotTimeEntry($project, $stage, $user, [
        'entry_date' => '2026-04-01',
        'duration_minutes' => 60,
        'is_billable' => true,
    ]);

    $this->getJson('/api/bot/time-entries/summary?month=2026-03')
        ->assertOk()
        ->assertJson([
            'range' => ['from' => '2026-03-01', 'to' => '2026-03-31'],
            'billable_hours' => 3.0,
        ]);
});

test('bot time entries summary rejects a malformed month', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/bot/time-entries/summary?month=not-a-month')
        ->assertUnprocessable();
});

test('bot time entries summary rejects requests without a valid token', function () {
    $this->getJson('/api/bot/time-entries/summary')->assertUnauthorized();
});

test('bot time entries summary returns zeroed totals when there are no entries', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/bot/time-entries/summary')
        ->assertOk()
        ->assertJson([
            'billable_hours' => 0,
            'non_billable_hours' => 0,
            'total_hours' => 0,
            'billable_amount' => 0,
        ]);
});
