<?php

use App\Models\Project;
use App\Models\User;

test('a bot sanctum token cannot authenticate against session-guarded write routes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('bot')->plainTextToken;

    // Web routes are guarded by the session-based `auth` guard, not `sanctum`,
    // so a genuine bot Bearer token must not grant access to them.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->post(route('projects.store'), [
            'code' => 'SHOULD-NOT-EXIST',
            'name' => 'Should not be created',
            'status' => 'active',
        ])
        ->assertRedirect(route('login'));

    expect(Project::query()->where('code', 'SHOULD-NOT-EXIST')->exists())->toBeFalse();
});

test('a bot sanctum token can authenticate against the bot API routes', function () {
    $user = User::factory()->create();
    $token = $user->createToken('bot')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/bot/projects')
        ->assertOk();
});
