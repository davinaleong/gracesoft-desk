<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Database\Seeders\LedgerPrototypeSeeder;

test('ledger prototype seeder imports accounts categories methods and transactions', function () {
    $this->seed(LedgerPrototypeSeeder::class);

    expect(Account::query()->where('code', 'CARD-CITIBANK')->exists())->toBeTrue()
        ->and(PaymentMethod::query()->where('slug', 'credit-card')->exists())->toBeTrue()
        ->and(TransactionCategory::query()->where('slug', 'professional-fees')->exists())->toBeTrue()
        ->and(Transaction::query()->count())->toBe(21);

    $zoomAnnual = Transaction::query()->where('transaction_code', 'TXN-2026-0011')->first();

    expect($zoomAnnual)->not()->toBeNull()
        ->and((string) $zoomAnnual->type)->toBe('expense')
        ->and((string) $zoomAnnual->direction)->toBe('out')
        ->and((string) $zoomAnnual->status)->toBe('completed')
        ->and((string) $zoomAnnual->transaction_date->toDateString())->toBe('2026-04-17');

    $this->seed(LedgerPrototypeSeeder::class);

    expect(Transaction::query()->count())->toBe(21);
});
