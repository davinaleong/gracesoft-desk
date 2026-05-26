<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;

function readyUserForTimeEntries(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @return array{project: Project, stage: ProjectStage}
 */
function timeEntryDependencies(): array
{
    $project = Project::query()->create([
        'code' => 'PRJ-TME-001',
        'name' => 'Time Entry Core Project',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $stage = ProjectStage::query()->create([
        'name' => 'Development',
        'sort_order' => 4,
        'status' => 'active',
    ]);

    return compact('project', 'stage');
}

test('time entries index is accessible', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 90,
        'is_billable' => true,
        'hourly_rate' => 120,
        'billable_amount' => 180,
        'notes' => 'Initial coding block',
    ]);

    $response = $this->actingAs($user)->get(route('time-entries.index'));

    $response->assertOk()->assertSee('PRJ-TME-001');
});

test('time entry can be created and billable amount is calculated', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    $response = $this->actingAs($user)->post(route('time-entries.store'), [
        'project_uuid' => $dependencies['project']->uuid,
        'project_stage_uuid' => $dependencies['stage']->uuid,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 120,
        'is_billable' => true,
        'hourly_rate' => 150,
        'notes' => 'Feature implementation sprint',
    ]);

    $timeEntry = TimeEntry::query()->firstOrFail();

    $response->assertRedirect(route('time-entries.index'));

    expect((float) $timeEntry->billable_amount)->toBe(300.0)
        ->and($timeEntry->user_id)->toBe($user->id);
});

test('time entry model enforces duration and billable amount calculations', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    $timeEntry = TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 90,
        'is_billable' => true,
        'hourly_rate' => 120,
        'billable_amount' => 1,
    ]);

    expect((float) $timeEntry->billable_amount)->toBe(180.0);

    $timeEntry->update([
        'duration_minutes' => 30,
        'hourly_rate' => 120,
    ]);

    $timeEntry->refresh();

    expect((float) $timeEntry->billable_amount)->toBe(60.0);

    $timeEntry->update([
        'is_billable' => false,
        'duration_minutes' => 45,
        'hourly_rate' => 99,
    ]);

    $timeEntry->refresh();

    expect((float) $timeEntry->billable_amount)->toBe(0.0);
});

test('time entry detail resolves by uuid not sql id', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    $timeEntry = TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 75,
        'is_billable' => false,
        'hourly_rate' => 0,
        'billable_amount' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('time-entries.show', $timeEntry))
        ->assertOk();

    $this->actingAs($user)
        ->get('/time-entries/'.$timeEntry->getRawOriginal('id'))
        ->assertNotFound();
});

test('time entry can be updated', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    $timeEntry = TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 30,
        'is_billable' => true,
        'hourly_rate' => 100,
        'billable_amount' => 50,
        'notes' => 'Initial note',
    ]);

    $response = $this->actingAs($user)->put(route('time-entries.update', $timeEntry), [
        'project_uuid' => $dependencies['project']->uuid,
        'project_stage_uuid' => $dependencies['stage']->uuid,
        'entry_date' => now()->subDay()->toDateString(),
        'duration_minutes' => 180,
        'is_billable' => true,
        'hourly_rate' => 80,
        'notes' => 'Extended implementation work',
    ]);

    $response->assertRedirect(route('time-entries.index'));

    $timeEntry->refresh();

    expect($timeEntry->duration_minutes)->toBe(180)
        ->and((float) $timeEntry->billable_amount)->toBe(240.0)
        ->and($timeEntry->notes)->toBe('Extended implementation work');
});

test('time entry can be deleted', function () {
    $user = readyUserForTimeEntries();
    $dependencies = timeEntryDependencies();

    $timeEntry = TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 45,
        'is_billable' => false,
        'hourly_rate' => 0,
        'billable_amount' => 0,
    ]);

    $response = $this->actingAs($user)->delete(route('time-entries.destroy', $timeEntry));

    $response->assertRedirect(route('time-entries.index'));

    $this->assertSoftDeleted('time_entries', [
        'uuid' => $timeEntry->uuid,
    ]);
});

test('time entries routes require authentication', function () {
    $dependencies = timeEntryDependencies();

    $timeEntry = TimeEntry::query()->create([
        'project_id' => $dependencies['project']->id,
        'project_stage_id' => $dependencies['stage']->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 60,
        'is_billable' => false,
        'hourly_rate' => 0,
        'billable_amount' => 0,
    ]);

    $response = $this->get(route('time-entries.show', $timeEntry));

    $response->assertRedirect(route('login'));
});
