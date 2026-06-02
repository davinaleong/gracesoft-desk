<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('a second user cannot be created via the eloquent model', function () {
    User::factory()->create();

    expect(fn () => User::factory()->create())
        ->toThrow(ValidationException::class, 'GraceSoft Desk supports only one admin account.');
});

test('CreateNewUser action blocks creation when a user already exists', function () {
    User::factory()->create();

    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(ValidationException::class, 'GraceSoft Desk supports only one admin account.');
});

test('exactly one user can be created via the eloquent model', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    expect(User::query()->count())->toBe(1);
});
