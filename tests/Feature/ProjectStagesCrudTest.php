<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;

function readyUserForStages(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('project stages index is accessible', function () {
    $user = readyUserForStages();

    ProjectStage::query()->create([
        'name' => 'Integration',
        'sort_order' => 10,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('settings.project-stages.index'))
        ->assertOk()
        ->assertSee('Integration');
});

test('project stage can be created with keywords', function () {
    $user = readyUserForStages();

    $response = $this->actingAs($user)->post(route('settings.project-stages.store'), [
        'name' => 'QA Review',
        'status' => 'active',
        'keywords' => 'qa, review, test, verify',
        'is_default' => false,
    ]);

    $stage = ProjectStage::query()->where('name', 'QA Review')->firstOrFail();

    $response->assertRedirect(route('settings.project-stages.index'));

    expect($stage->keywords)->toContain('qa')
        ->and($stage->keywords)->toContain('review')
        ->and($stage->is_default)->toBeFalse();
});

test('project stage keywords are normalised to lowercase trimmed array', function () {
    $user = readyUserForStages();

    $this->actingAs($user)->post(route('settings.project-stages.store'), [
        'name' => 'Release',
        'status' => 'active',
        'keywords' => '  Release , Deploy ,  SHIP ',
        'is_default' => false,
    ]);

    $stage = ProjectStage::query()->where('name', 'Release')->firstOrFail();

    expect($stage->keywords)->toBe(['release', 'deploy', 'ship']);
});

test('project stage can be updated', function () {
    $user = readyUserForStages();

    $stage = ProjectStage::query()->create([
        'name' => 'Draft',
        'sort_order' => 20,
        'status' => 'active',
    ]);

    $this->actingAs($user)->put(route('settings.project-stages.update', $stage), [
        'name' => 'Drafting',
        'status' => 'inactive',
        'keywords' => 'draft, wip',
        'is_default' => true,
    ])->assertRedirect(route('settings.project-stages.index'));

    $stage->refresh();

    expect($stage->name)->toBe('Drafting')
        ->and($stage->status)->toBe('inactive')
        ->and($stage->is_default)->toBeTrue()
        ->and($stage->keywords)->toBe(['draft', 'wip']);
});

test('project stage can be deleted when unused', function () {
    $user = readyUserForStages();

    $stage = ProjectStage::query()->create([
        'name' => 'Obsolete',
        'sort_order' => 99,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->delete(route('settings.project-stages.destroy', $stage))
        ->assertRedirect(route('settings.project-stages.index'));

    expect(ProjectStage::query()->where('name', 'Obsolete')->exists())->toBeFalse();
});

test('project stage cannot be deleted when time entries are assigned', function () {
    $user = readyUserForStages();

    $stage = ProjectStage::query()->create([
        'name' => 'In Use',
        'sort_order' => 88,
        'status' => 'active',
    ]);

    $project = Project::query()->create([
        'code' => 'STG-PRJ-001',
        'name' => 'Stage Test Project',
        'status' => 'active',
        'is_billable' => false,
        'hourly_rate' => 0,
    ]);

    TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 30,
        'is_billable' => false,
    ]);

    $this->actingAs($user)
        ->delete(route('settings.project-stages.destroy', $stage))
        ->assertRedirect(route('settings.project-stages.index'))
        ->assertSessionHas('status', 'project-stage-in-use');

    expect(ProjectStage::query()->where('name', 'In Use')->exists())->toBeTrue();
});

test('project stage sort order can be changed with move up and move down', function () {
    $user = readyUserForStages();

    $a = ProjectStage::query()->create(['name' => 'Alpha', 'sort_order' => 100, 'status' => 'active']);
    $b = ProjectStage::query()->create(['name' => 'Beta', 'sort_order' => 101, 'status' => 'active']);
    $c = ProjectStage::query()->create(['name' => 'Gamma', 'sort_order' => 102, 'status' => 'active']);

    // Move Beta up — it should swap with Alpha
    $this->actingAs($user)
        ->patch(route('settings.project-stages.move-up', $b))
        ->assertRedirect(route('settings.project-stages.index'));

    expect($b->fresh()->sort_order)->toBe(100)
        ->and($a->fresh()->sort_order)->toBe(101);

    // Move Beta back down — swaps with Alpha (now at 101)
    $this->actingAs($user)
        ->patch(route('settings.project-stages.move-down', $b))
        ->assertRedirect(route('settings.project-stages.index'));

    expect($b->fresh()->sort_order)->toBe(101)
        ->and($a->fresh()->sort_order)->toBe(100)
        ->and($c->fresh()->sort_order)->toBe(102);
});

test('project stages routes require authentication', function () {
    $this->get(route('settings.project-stages.index'))->assertRedirect(route('login'));
});
