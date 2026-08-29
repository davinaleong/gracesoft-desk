<?php

use App\Models\Project;
use App\Models\User;

function readyUser(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('projects index is accessible', function () {
    $user = readyUser();

    Project::factory()->create([
        'code' => 'PRJ-OPS-001',
        'name' => 'Operations Migration',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index'));

    $response->assertOk()->assertSee('PRJ-OPS-001');
});

test('projects index shows github link status', function () {
    $user = readyUser();

    Project::factory()->create([
        'code' => 'PRJ-LINKED-001',
        'name' => 'Linked Project',
        'github_repo' => 'octocat/hello-world',
        'github_branch' => 'main',
    ]);

    Project::factory()->create([
        'code' => 'PRJ-UNLINKED-001',
        'name' => 'Unlinked Project',
    ]);

    $response = $this->actingAs($user)->get(route('projects.index'));

    $response->assertOk()
        ->assertSee('Linked')
        ->assertSee('octocat/hello-world')
        ->assertSee('Not linked');
});

test('project can be created with manual code', function () {
    $user = readyUser();

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'code' => 'prj-fin-001',
        'name' => 'Finance Dashboard Build',
        'status' => 'active',
        'description' => 'Initial finance cockpit project.',
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'is_billable' => true,
    ]);

    $project = Project::query()->firstOrFail();

    $response->assertRedirect(route('projects.show', $project));

    expect($project->code)->toBe('PRJ-FIN-001');
});

test('project detail resolves by uuid not sql id', function () {
    $user = readyUser();

    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk();

    $this->actingAs($user)
        ->get('/projects/'.$project->getRawOriginal('id'))
        ->assertNotFound();
});

test('project can be updated', function () {
    $user = readyUser();

    $project = Project::factory()->create([
        'code' => 'PRJ-OLD-001',
        'name' => 'Old Name',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => 'PRJ-NEW-001',
        'name' => 'New Name',
        'status' => 'completed',
        'description' => 'Updated details',
        'starts_on' => now()->subDays(5)->toDateString(),
        'ends_on' => now()->addDays(5)->toDateString(),
        'is_billable' => false,
    ]);

    $response->assertRedirect(route('projects.show', $project));

    $project->refresh();

    expect($project->code)->toBe('PRJ-NEW-001')
        ->and($project->name)->toBe('New Name')
        ->and($project->status)->toBe('completed')
        ->and($project->is_billable)->toBeFalse();
});

test('projects routes require authentication', function () {
    $project = Project::factory()->create();

    $response = $this->get(route('projects.show', $project));

    $response->assertRedirect(route('login'));
});

test('project show page prompts to create another after creation', function () {
    $user = readyUser();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->withSession(['status' => 'project-created'])
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee(__('Create Another'));
});
