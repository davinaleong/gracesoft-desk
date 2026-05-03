<?php

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
    ]);

    $response->assertRedirect(route('settings.system.edit'));

    expect(SystemSetting::query()->where('key', 'company_name')->value('value'))->toBe('GraceSoft Desk Pte Ltd')
        ->and(SystemSetting::query()->where('key', 'timezone')->value('value'))->toBe('Asia/Singapore')
        ->and((float) SystemSetting::query()->where('key', 'default_hourly_rate')->value('value'))->toBe(125.5);
});

test('system settings routes require authentication', function () {
    $response = $this->get(route('settings.system.edit'));

    $response->assertRedirect(route('login'));
});
