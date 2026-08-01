<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionCategory;

function makeTestTransaction(string $code, string $date): Transaction
{
    $account = Account::query()->first() ?? Account::query()->create([
        'name' => 'Test Account',
        'code' => 'TST-ACC-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->first() ?? TransactionCategory::query()->create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'type' => 'expense',
        'is_active' => true,
    ]);

    $transaction = new Transaction([
        'account_id' => $account->id,
        'transaction_category_id' => $category->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => $date,
        'amount' => 100.00,
        'gst_amount' => 0,
        'net_amount' => 100.00,
    ]);

    $transaction->transaction_code = $code;
    $transaction->save();

    return $transaction;
}

test('recalculate transaction codes command regenerates codes using standard format', function () {
    $t1 = makeTestTransaction('TRX-OLD-001', '2026-05-10');
    $t2 = makeTestTransaction('TRX-OLD-002', '2026-06-15');

    $this->artisan('desk:recalculate-transaction-codes')
        ->expectsOutputToContain('Recalculating transaction codes for 2 transaction(s)')
        ->expectsOutputToContain('recalculated successfully')
        ->assertExitCode(0);

    $t1->refresh();
    $t2->refresh();

    expect($t1->transaction_code)->toMatch('/^TRX-20260510-[A-Z0-9]{6}$/')
        ->and($t2->transaction_code)->toMatch('/^TRX-20260615-[A-Z0-9]{6}$/');
});

test('recalculate transaction codes command handles empty table', function () {
    $this->artisan('desk:recalculate-transaction-codes')
        ->expectsOutputToContain('No transactions found')
        ->assertExitCode(0);
});

test('recalculate transaction codes command produces unique codes', function () {
    makeTestTransaction('DUPE-001', '2026-07-01');
    makeTestTransaction('DUPE-002', '2026-07-01');
    makeTestTransaction('DUPE-003', '2026-07-01');

    $this->artisan('desk:recalculate-transaction-codes')->assertExitCode(0);

    $codes = Transaction::query()->pluck('transaction_code');

    expect($codes->unique()->count())->toBe($codes->count());
});
