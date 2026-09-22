<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('bot finance summary reports income, expense, and cash position for a valid token', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    Account::query()->create([
        'name' => 'Main Bank',
        'code' => 'BOT-ACC-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 5000,
        'is_active' => true,
    ]);

    Transaction::factory()->create([
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->subDays(5)->toDateString(),
        'amount' => 1000,
        'gst_amount' => 0,
        'net_amount' => 1000,
    ]);

    Transaction::factory()->create([
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->subDays(3)->toDateString(),
        'amount' => 400,
        'gst_amount' => 0,
        'net_amount' => 400,
    ]);

    // Outside the rolling 30-day window — must not be counted.
    Transaction::factory()->create([
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->subDays(45)->toDateString(),
        'amount' => 5000,
        'gst_amount' => 0,
        'net_amount' => 5000,
    ]);

    $this->getJson('/api/bot/finance/summary')
        ->assertOk()
        ->assertJson([
            'income' => 1000.0,
            'expense' => 400.0,
            'net' => 600.0,
            'cash_position' => 5000.0,
        ]);
});

test('bot finance summary accepts a month override', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    Transaction::factory()->create([
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => '2026-03-10',
        'amount' => 750,
        'gst_amount' => 0,
        'net_amount' => 750,
    ]);

    $this->getJson('/api/bot/finance/summary?month=2026-03')
        ->assertOk()
        ->assertJson([
            'range' => ['from' => '2026-03-01', 'to' => '2026-03-31'],
            'income' => 750.0,
        ]);
});

test('bot finance summary rejects a malformed month', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/bot/finance/summary?month=2026-13')
        ->assertUnprocessable();
});

test('bot finance summary rejects requests without a valid token', function () {
    $this->getJson('/api/bot/finance/summary')->assertUnauthorized();
});

test('bot finance summary returns zeroed totals when there is no data', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/bot/finance/summary')
        ->assertOk()
        ->assertJson([
            'income' => 0,
            'expense' => 0,
            'net' => 0,
            'cash_position' => 0,
        ]);
});
