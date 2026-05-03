<?php

use App\Models\User;

test('dashboard is blocked until password is changed', function () {
    $user = User::factory()->create([
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('profile.edit'));
});

test('dashboard is accessible after password is changed', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
