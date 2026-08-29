<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates the first account interactively', function () {
    $this->artisan('desk:make-user')
        ->expectsQuestion('Name', 'Grace Soft')
        ->expectsQuestion('Email', 'grace@gracesoft.dev')
        ->expectsQuestion('Password', 'CorrectHorseBattery9!')
        ->expectsQuestion('Confirm password', 'CorrectHorseBattery9!')
        ->expectsOutputToContain('Created account: Grace Soft <grace@gracesoft.dev>')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'grace@gracesoft.dev')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Grace Soft')
        ->and(Hash::check('CorrectHorseBattery9!', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->password_changed_at)->not->toBeNull();
});

test('creates the first account non-interactively via options', function () {
    $this->artisan('desk:make-user', [
        '--name' => 'Grace Soft',
        '--email' => 'grace@gracesoft.dev',
        '--password' => 'CorrectHorseBattery9!',
    ])
        ->expectsOutputToContain('Created account: Grace Soft <grace@gracesoft.dev>')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'grace@gracesoft.dev')->exists())->toBeTrue();
});

test('--must-change-password forces a change on next login', function () {
    $this->artisan('desk:make-user', [
        '--name' => 'Grace Soft',
        '--email' => 'grace@gracesoft.dev',
        '--password' => 'CorrectHorseBattery9!',
        '--must-change-password' => true,
    ])->assertExitCode(0);

    $user = User::query()->where('email', 'grace@gracesoft.dev')->first();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->password_changed_at)->toBeNull();
});

test('rejects an invalid email', function () {
    $this->artisan('desk:make-user', [
        '--name' => 'Grace Soft',
        '--email' => 'not-an-email',
        '--password' => 'CorrectHorseBattery9!',
    ])
        ->expectsOutputToContain('email')
        ->assertExitCode(1);

    expect(User::query()->exists())->toBeFalse();
});

test('rejects a weak password', function () {
    $this->artisan('desk:make-user', [
        '--name' => 'Grace Soft',
        '--email' => 'grace@gracesoft.dev',
        '--password' => '123',
    ])->assertExitCode(1);

    expect(User::query()->exists())->toBeFalse();
});

test('asks for confirmation before overwriting an existing account', function () {
    $existing = User::factory()->create(['name' => 'Old Admin', 'email' => 'old@gracesoft.dev']);

    $this->artisan('desk:make-user', [
        '--name' => 'New Admin',
        '--email' => 'new@gracesoft.dev',
        '--password' => 'CorrectHorseBattery9!',
    ])
        ->expectsConfirmation('Overwrite it with new credentials?', 'no')
        ->expectsOutputToContain('Cancelled')
        ->assertExitCode(0);

    $existing->refresh();
    expect($existing->email)->toBe('old@gracesoft.dev')
        ->and(User::query()->count())->toBe(1);
});

test('overwrites the existing account when confirmed', function () {
    User::factory()->create([
        'name' => 'Old Admin',
        'email' => 'old@gracesoft.dev',
        'must_change_password' => true,
        'two_factor_confirmed_at' => now(),
        'failed_login_attempts' => 3,
    ]);

    $this->artisan('desk:make-user', [
        '--name' => 'New Admin',
        '--email' => 'new@gracesoft.dev',
        '--password' => 'CorrectHorseBattery9!',
    ])
        ->expectsConfirmation('Overwrite it with new credentials?', 'yes')
        ->expectsOutputToContain('Updated account: New Admin <new@gracesoft.dev>')
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(1);

    $user = User::query()->first();
    expect($user->name)->toBe('New Admin')
        ->and($user->email)->toBe('new@gracesoft.dev')
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->failed_login_attempts)->toBe(0);
});

test('--force skips the overwrite confirmation', function () {
    User::factory()->create(['name' => 'Old Admin', 'email' => 'old@gracesoft.dev']);

    $this->artisan('desk:make-user', [
        '--name' => 'New Admin',
        '--email' => 'new@gracesoft.dev',
        '--password' => 'CorrectHorseBattery9!',
        '--force' => true,
    ])
        ->expectsOutputToContain('Updated account: New Admin <new@gracesoft.dev>')
        ->assertExitCode(0);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->first()->email)->toBe('new@gracesoft.dev');
});
