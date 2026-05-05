<?php

use App\Models\User;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('app.debug', false);
    config()->set('session.secure', true);
    config()->set('session.http_only', true);
    config()->set('session.same_site', 'strict');

    $backupPath = storage_path('app/backups-test');

    if (! is_dir($backupPath)) {
        mkdir($backupPath, 0777, true);
    }

    putenv('DESK_BACKUP_PATH='.$backupPath);
    $_ENV['DESK_BACKUP_PATH'] = $backupPath;
    $_SERVER['DESK_BACKUP_PATH'] = $backupPath;

    putenv('ADMIN_EMAIL=admin@gracesoft.local');
    $_ENV['ADMIN_EMAIL'] = 'admin@gracesoft.local';
    $_SERVER['ADMIN_EMAIL'] = 'admin@gracesoft.local';
});

test('prelaunch check command succeeds when hardening checks pass', function () {
    User::factory()->create([
        'email' => 'admin@gracesoft.local',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    putenv('ADMIN_TEMP_PASSWORD=StrongTempPassword!2026');
    $_ENV['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';
    $_SERVER['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('Prelaunch checks passed')
        ->assertExitCode(0);
});

test('prelaunch check command fails when app debug is enabled', function () {
    User::factory()->create([
        'email' => 'admin@gracesoft.local',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    config()->set('app.debug', true);

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('Prelaunch checks failed')
        ->assertExitCode(1);
});

test('prelaunch check command can seed admin when option is provided', function () {
    User::query()->where('email', 'admin@gracesoft.local')->delete();

    putenv('ADMIN_TEMP_PASSWORD=StrongTempPassword!2026');
    $_ENV['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';
    $_SERVER['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';

    $this->artisan('desk:prelaunch-check --seed-admin')
        ->expectsOutputToContain('Admin user seed executed')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'admin@gracesoft.local')->exists())->toBeTrue();
});
