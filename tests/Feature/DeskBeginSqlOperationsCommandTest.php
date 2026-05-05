<?php

use App\Models\SystemSetting;

test('begin sql-first operations command updates operation settings', function () {
    $this->artisan('desk:begin-sql-operations')
        ->expectsOutputToContain('SQL-first operations mode is now active')
        ->assertExitCode(0);

    expect(SystemSetting::query()->where('key', 'archive_mode')->value('value'))->toBe('0')
        ->and(SystemSetting::query()->where('key', 'operations_mode')->value('value'))->toBe('sql-first')
        ->and(SystemSetting::query()->where('key', 'sql_operations_started_at')->value('value'))->not()->toBeNull();
});

test('begin sql-first operations command warns if already started without force', function () {
    SystemSetting::upsertValues([
        'sql_operations_started_at' => '2026-05-01 10:00:00',
    ]);

    $this->artisan('desk:begin-sql-operations')
        ->expectsOutputToContain('already started')
        ->assertExitCode(0);

    expect(SystemSetting::query()->where('key', 'sql_operations_started_at')->value('value'))
        ->toBe('2026-05-01 10:00:00');
});
