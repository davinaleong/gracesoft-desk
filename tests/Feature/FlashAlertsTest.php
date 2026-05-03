<?php

use App\Models\User;

function readyUserForFlashAlerts(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('global flash alert is rendered from status key', function () {
    $user = readyUserForFlashAlerts();

    $this->actingAs($user)
        ->withSession(['status' => 'project-created'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Project created successfully.');
});
