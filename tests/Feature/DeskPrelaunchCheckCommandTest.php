<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('app.debug', false);
    config()->set('session.secure', true);
    config()->set('session.http_only', true);
    config()->set('session.same_site', 'strict');
    config()->set('app.url', 'https://gracesoft.test');
    config()->set('mail.from.address', 'ops@gracesoft.dev');
    config()->set('queue.default', 'database');
    config()->set('mail.default', 'smtp');
    config()->set('filesystems.disks.s3.bucket', 'desk-test');
    SystemSetting::upsertValues(['company_address' => '1 Raffles Place, Singapore']);
    Cache::forever('desk.scheduler.last_run', now()->toIso8601String());

    $backupPath = storage_path('app/backups-test');

    if (! is_dir($backupPath)) {
        mkdir($backupPath, 0777, true);
    }

    putenv('DESK_BACKUP_PATH='.$backupPath);
    $_ENV['DESK_BACKUP_PATH'] = $backupPath;
    $_SERVER['DESK_BACKUP_PATH'] = $backupPath;

    putenv('ADMIN_EMAIL=admin@gracesoft.dev');
    $_ENV['ADMIN_EMAIL'] = 'admin@gracesoft.dev';
    $_SERVER['ADMIN_EMAIL'] = 'admin@gracesoft.dev';
});

test('prelaunch check command succeeds when hardening checks pass', function () {
    User::factory()->create([
        'email' => 'admin@gracesoft.dev',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    putenv('ADMIN_TEMP_PASSWORD=StrongTempPassword!2026');
    $_ENV['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';
    $_SERVER['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('all pre-launch checks passed')
        ->assertExitCode(0);
});

test('prelaunch check command fails when app debug is enabled', function () {
    User::factory()->create([
        'email' => 'admin@gracesoft.dev',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    config()->set('app.debug', true);

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('critical issue')
        ->assertExitCode(1);
});

test('prelaunch check command can seed admin when option is provided', function () {
    User::query()->where('email', 'admin@gracesoft.dev')->delete();

    putenv('ADMIN_TEMP_PASSWORD=StrongTempPassword!2026');
    $_ENV['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';
    $_SERVER['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';

    $this->artisan('desk:prelaunch-check --seed-admin')
        ->expectsOutputToContain('Admin account has been seeded/updated')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'admin@gracesoft.dev')->exists())->toBeTrue();
});

test('prelaunch check command fails in strict mode when warnings exist', function () {
    User::factory()->create([
        'email' => 'admin@gracesoft.dev',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);

    config()->set('queue.default', 'sync');
    putenv('ADMIN_TEMP_PASSWORD=StrongTempPassword!2026');
    $_ENV['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';
    $_SERVER['ADMIN_TEMP_PASSWORD'] = 'StrongTempPassword!2026';

    $this->artisan('desk:prelaunch-check --strict')
        ->expectsOutputToContain('Strict mode stopped the pre-launch check')
        ->assertExitCode(1);
});

test('prelaunch check warns when invoicing is not ready to send', function () {
    config()->set('mail.default', 'log');
    config()->set('filesystems.disks.s3.bucket', null);
    SystemSetting::upsertValues(['company_address' => null]);

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('Mail transport delivers real email')
        ->expectsOutputToContain('S3 bucket is configured')
        ->expectsOutputToContain('Company address is set for invoices')
        ->expectsOutputToContain('warning(s)');
});

test('prelaunch check warns when the scheduler has not run recently', function () {
    Cache::forever('desk.scheduler.last_run', now()->subHours(3)->toIso8601String());

    $this->artisan('desk:prelaunch-check')
        ->expectsOutputToContain('Scheduler ran in the last hour')
        ->expectsOutputToContain('warning(s)');
});
