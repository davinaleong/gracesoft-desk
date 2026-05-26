<?php

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;

function readyUserForAuditLogging(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('create update and delete actions are persisted to audit logs', function () {
    $user = readyUserForAuditLogging();

    $project = Project::query()->create([
        'code' => 'PRJ-AUD-001',
        'name' => 'Audit Trail Project',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $stage = ProjectStage::query()->create([
        'name' => 'Design',
        'sort_order' => 3,
        'status' => 'active',
    ]);

    $createResponse = $this->actingAs($user)->post(route('time-entries.store'), [
        'project_uuid' => $project->uuid,
        'project_stage_uuid' => $stage->uuid,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 60,
        'is_billable' => true,
        'hourly_rate' => 120,
        'notes' => 'Initial audited entry',
    ]);

    $createResponse->assertRedirect(route('time-entries.index'));

    $timeEntry = TimeEntry::query()->where('notes', 'Initial audited entry')->firstOrFail();

    $updateResponse = $this->actingAs($user)->put(route('time-entries.update', $timeEntry), [
        'project_uuid' => $project->uuid,
        'project_stage_uuid' => $stage->uuid,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 90,
        'is_billable' => true,
        'hourly_rate' => 120,
        'notes' => 'Updated audited entry',
    ]);

    $updateResponse->assertRedirect(route('time-entries.index'));

    $deleteResponse = $this->actingAs($user)->delete(route('time-entries.destroy', $timeEntry));

    $deleteResponse->assertRedirect(route('time-entries.index'));

    $auditableId = $timeEntry->getRawOriginal('id');

    expect(AuditLog::query()
        ->where('auditable_type', TimeEntry::class)
        ->where('auditable_id', $auditableId)
        ->where('user_id', $user->getRawOriginal('id'))
        ->where('action', 'created')
        ->exists())->toBeTrue();

    $updatedLog = AuditLog::query()
        ->where('auditable_type', TimeEntry::class)
        ->where('auditable_id', $auditableId)
        ->where('action', 'updated')
        ->latest('id')
        ->firstOrFail();

    expect($updatedLog->old_values)->toHaveKey('duration_minutes')
        ->and($updatedLog->new_values)->toHaveKey('duration_minutes')
        ->and((int) $updatedLog->old_values['duration_minutes'])->toBe(60)
        ->and((int) $updatedLog->new_values['duration_minutes'])->toBe(90)
        ->and(AuditLog::query()
            ->where('auditable_type', TimeEntry::class)
            ->where('auditable_id', $auditableId)
            ->where('action', 'deleted')
            ->exists())->toBeTrue();
});
