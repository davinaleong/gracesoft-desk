<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;

function readyUserForTransactions(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @return array{account: Account, category: TransactionCategory, paymentMethod: PaymentMethod, project: Project}
 */
function transactionDependencies(): array
{
    $account = Account::query()->create([
        'name' => 'Ops Bank',
        'code' => 'BANK-OPS-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Operations Expense',
        'slug' => 'operations-expense',
        'type' => 'expense',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Bank Transfer',
        'slug' => 'bank-transfer-local',
        'is_active' => true,
    ]);

    $project = Project::query()->create([
        'code' => 'PRJ-TXN-001',
        'name' => 'Transaction Module Project',
        'status' => 'active',
        'is_billable' => false,
    ]);

    return compact('account', 'category', 'paymentMethod', 'project');
}

test('transactions index is accessible', function () {
    $user = readyUserForTransactions();
    $dependencies = transactionDependencies();

    Transaction::query()->create([
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 120,
        'gst_amount' => 10,
        'net_amount' => 110,
    ]);

    $response = $this->actingAs($user)->get(route('transactions.index'));

    $response->assertOk()->assertSee('TRX-');
});

test('transaction can be created with generated transaction code', function () {
    $user = readyUserForTransactions();
    $dependencies = transactionDependencies();

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'reference' => 'INV-1001',
        'description' => 'Software subscription',
        'amount' => 100,
        'gst_amount' => 9,
    ]);

    $transaction = Transaction::query()->firstOrFail();

    $response->assertRedirect(route('transactions.show', $transaction));

    expect($transaction->transaction_code)->toStartWith('TRX-')
        ->and((float) $transaction->net_amount)->toBe(91.0);
});

test('transaction detail resolves by uuid not sql id', function () {
    $user = readyUserForTransactions();
    $dependencies = transactionDependencies();

    $transaction = Transaction::query()->create([
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 500,
        'gst_amount' => 0,
        'net_amount' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk();

    $this->actingAs($user)
        ->get('/transactions/'.$transaction->getRawOriginal('id'))
        ->assertNotFound();
});

test('transaction can be updated', function () {
    $user = readyUserForTransactions();
    $dependencies = transactionDependencies();

    $transaction = Transaction::query()->create([
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'pending',
        'transaction_date' => now()->toDateString(),
        'amount' => 50,
        'gst_amount' => 5,
        'net_amount' => 45,
    ]);

    $response = $this->actingAs($user)->put(route('transactions.update', $transaction), [
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'reference' => 'INV-2001',
        'description' => 'Updated expense',
        'amount' => 200,
        'gst_amount' => 18,
    ]);

    $response->assertRedirect(route('transactions.show', $transaction));

    $transaction->refresh();

    expect($transaction->status)->toBe('completed')
        ->and((float) $transaction->amount)->toBe(200.0)
        ->and((float) $transaction->gst_amount)->toBe(18.0)
        ->and((float) $transaction->net_amount)->toBe(182.0);
});

test('transactions routes require authentication', function () {
    $dependencies = transactionDependencies();

    $transaction = Transaction::query()->create([
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 95,
        'gst_amount' => 0,
        'net_amount' => 95,
    ]);

    $response = $this->get(route('transactions.show', $transaction));

    $response->assertRedirect(route('login'));
});
