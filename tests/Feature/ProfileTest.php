<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information update endpoint is disabled', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response->assertMethodNotAllowed();
});

test('profile update remains disabled even when email is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response->assertMethodNotAllowed();
});

test('user account deletion endpoint is disabled', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();
    $this->assertNotNull($user->fresh());
});

test('delete endpoint remains disabled regardless of password validity', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response->assertMethodNotAllowed();

    $this->assertNotNull($user->fresh());
});

test('profile page shows recovery codes ui when two-factor is enabled', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode([
            'alpha-recovery-code',
            'bravo-recovery-code',
        ], JSON_THROW_ON_ERROR)),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk()
        ->assertSeeText('Recovery Codes')
        ->assertSeeText('alpha-recovery-code')
        ->assertSeeText('bravo-recovery-code');
});
