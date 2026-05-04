<?php

use App\Models\User;

function readyUserForAccessibility(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('authenticated pages include keyboard and screen reader accessibility affordances', function () {
    $user = readyUserForAccessibility();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Skip to main content')
        ->assertSee('id="main-content"', false)
        ->assertSee('scope="col"', false)
        ->assertSee('caption class="sr-only"', false);
});
