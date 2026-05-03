<?php

use App\Models\Account;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('public models bind routes by uuid', function () {
    expect((new User)->getRouteKeyName())->toBe('uuid')
        ->and((new Project)->getRouteKeyName())->toBe('uuid')
        ->and((new Transaction)->getRouteKeyName())->toBe('uuid');
});

test('only one user can exist', function () {
    User::factory()->create();

    expect(fn () => User::factory()->create())->toThrow(ValidationException::class);
});

test('transactions generate public identifiers automatically', function () {
    $account = Account::query()->create([
        'name' => 'Main Account',
        'code' => 'BANK-TST',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $transaction = Transaction::query()->create([
        'account_id' => $account->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 250,
        'gst_amount' => 0,
        'net_amount' => 250,
    ]);

    expect($transaction->uuid)->not()->toBeEmpty()
        ->and($transaction->transaction_code)->toStartWith('TRX-');
});

test('public model serialization does not expose sql id', function () {
    $project = Project::query()->create([
        'code' => 'GS-TST-001',
        'name' => 'Public Serialization Check',
        'status' => 'active',
        'is_billable' => true,
    ]);

    expect($project->toArray())->not()->toHaveKey('id')
        ->and($project->toArray())->toHaveKey('uuid');
});
