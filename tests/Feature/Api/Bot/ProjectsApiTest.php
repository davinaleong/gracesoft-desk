<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('bot projects endpoint returns status and current stage for a valid token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $project = Project::factory()->create([
        'code' => 'PRJ-BOT-001',
        'name' => 'Bot Integration',
        'status' => 'active',
    ]);

    $stage = ProjectStage::query()->create([
        'name' => 'Development',
        'sort_order' => 4,
        'status' => 'active',
    ]);

    TimeEntry::factory()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
    ]);

    $this->getJson('/api/bot/projects')
        ->assertOk()
        ->assertJson([
            'projects' => [
                [
                    'code' => 'PRJ-BOT-001',
                    'name' => 'Bot Integration',
                    'status' => 'active',
                    'current_stage' => 'Development',
                ],
            ],
        ]);
});

test('bot projects endpoint rejects requests without a valid token', function () {
    Project::factory()->create();

    $this->getJson('/api/bot/projects')->assertUnauthorized();
});

test('bot projects endpoint returns an empty list when there are no projects', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/bot/projects')
        ->assertOk()
        ->assertJson(['projects' => []]);
});
