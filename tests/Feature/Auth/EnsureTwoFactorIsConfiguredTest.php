<?php

use App\Models\User;

test('dashboard is blocked until two-factor is confirmed', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('profile.edit'));
});

test('dashboard is accessible after two-factor confirmation', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
