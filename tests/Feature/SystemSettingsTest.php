<?php

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;

function readyUserForSystemSettings(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('system settings page is accessible for authenticated user', function () {
    $user = readyUserForSystemSettings();

    $response = $this->actingAs($user)->get(route('settings.system.edit'));

    $response->assertOk()->assertSee('System Settings');
});

test('system settings can be updated', function () {
    $user = readyUserForSystemSettings();

    $response = $this->actingAs($user)->put(route('settings.system.update'), [
        'company_name' => 'GraceSoft Desk Pte Ltd',
        'company_email' => 'ops@gracesoftdesk.com',
        'default_currency' => 'SGD',
        'timezone' => 'Asia/Singapore',
        'default_hourly_rate' => 125.50,
        'archive_mode' => false,
    ]);

    $response->assertRedirect(route('settings.system.edit'));

    expect(SystemSetting::query()->where('key', 'company_name')->value('value'))->toBe('GraceSoft Desk Pte Ltd')
        ->and(SystemSetting::query()->where('key', 'timezone')->value('value'))->toBe('Asia/Singapore')
        ->and((float) SystemSetting::query()->where('key', 'default_hourly_rate')->value('value'))->toBe(125.5)
        ->and(SystemSetting::query()->where('key', 'archive_mode')->value('value'))->toBe('0');
});

test('archive mode blocks write actions outside settings', function () {
    $user = readyUserForSystemSettings();

    SystemSetting::upsertValues([
        'archive_mode' => true,
    ]);

    $response = $this->from(route('projects.create'))
        ->actingAs($user)
        ->post(route('projects.store'), [
            'code' => 'PRJ-ARCH-001',
            'name' => 'Archive Blocked Project',
            'status' => 'active',
            'description' => 'Should not be created while archive mode is on.',
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'is_billable' => true,
        ]);

    $response->assertRedirect(route('projects.create'));

    expect(session('status'))->toBe('archive-mode-read-only')
        ->and(Project::query()->where('code', 'PRJ-ARCH-001')->exists())->toBeFalse();
});

test('archive mode can be disabled from system settings', function () {
    $user = readyUserForSystemSettings();

    SystemSetting::upsertValues([
        'company_name' => 'GraceSoft Desk',
        'company_email' => 'ops@gracesoftdesk.com',
        'default_currency' => 'SGD',
        'timezone' => 'Asia/Singapore',
        'default_hourly_rate' => '100',
        'archive_mode' => true,
    ]);

    $response = $this->actingAs($user)->put(route('settings.system.update'), [
        'company_name' => 'GraceSoft Desk',
        'company_email' => 'ops@gracesoftdesk.com',
        'default_currency' => 'SGD',
        'timezone' => 'Asia/Singapore',
        'default_hourly_rate' => 100,
        'archive_mode' => false,
    ]);

    $response->assertRedirect(route('settings.system.edit'));

    expect(SystemSetting::query()->where('key', 'archive_mode')->value('value'))->toBe('0');
});

test('system settings routes require authentication', function () {
    $response = $this->get(route('settings.system.edit'));

    $response->assertRedirect(route('login'));
});
