<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\DashboardMetricsService;

function readyUserForDashboard(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('dashboard displays aggregated metrics', function () {
    $user = readyUserForDashboard();

    $project = Project::query()->create([
        'code' => 'PRJ-DB-001',
        'name' => 'Dashboard Pilot Project',
        'status' => 'active',
        'is_billable' => true,
        'hourly_rate' => 100,
    ]);

    $stage = ProjectStage::query()->create([
        'name' => 'Development',
        'sort_order' => 4,
        'status' => 'active',
    ]);

    TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 120,
        'is_billable' => true,
    ]);

    $account = Account::query()->create([
        'name' => 'Dashboard Account',
        'code' => 'DB-ACC-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Dashboard Income',
        'slug' => 'dashboard-income',
        'type' => 'income',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Dashboard Transfer',
        'slug' => 'dashboard-transfer',
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'account_id' => $account->id,
        'transaction_category_id' => $category->id,
        'payment_method_id' => $paymentMethod->id,
        'project_id' => $project->id,
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 500,
        'gst_amount' => 0,
        'net_amount' => 500,
    ]);

    Transaction::query()->create([
        'account_id' => $account->id,
        'transaction_category_id' => $category->id,
        'payment_method_id' => $paymentMethod->id,
        'project_id' => $project->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'pending',
        'transaction_date' => now()->toDateString(),
        'amount' => 100,
        'gst_amount' => 0,
        'net_amount' => 100,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Active Projects')
        ->assertSee('Total Logged Hours')
        ->assertSee('Total Billable Value')
        ->assertSee('Monthly Cash Flow')
        ->assertSee('Money In (This Month)')
        ->assertSee('Money Out (This Month)')
        ->assertSee('SGD 200.00')
        ->assertSee('SGD 500.00')
        ->assertSee('SGD 0.00')
        ->assertSee('2h')
        ->assertSee('Expense Breakdown')
        ->assertSee('Income Breakdown')
        ->assertSee('Pending / Outstanding Transactions')
        ->assertSee('Billable by Project')
        ->assertSee('Billable by Stage')
        ->assertSee('PRJ-DB-001');
});

test('dashboard route requires authentication', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('monthly cashflow series separates money in and money out per month', function () {
    $account = Account::query()->create([
        'name' => 'Series Account',
        'code' => 'DB-ACC-002',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $incomeCategory = TransactionCategory::query()->create([
        'name' => 'Series Income',
        'slug' => 'series-income',
        'type' => 'income',
        'is_active' => true,
    ]);

    $expenseCategory = TransactionCategory::query()->create([
        'name' => 'Series Expense',
        'slug' => 'series-expense',
        'type' => 'expense',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Series Transfer',
        'slug' => 'series-transfer',
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'account_id' => $account->id,
        'transaction_category_id' => $incomeCategory->id,
        'payment_method_id' => $paymentMethod->id,
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 800,
        'gst_amount' => 0,
        'net_amount' => 800,
    ]);

    Transaction::query()->create([
        'account_id' => $account->id,
        'transaction_category_id' => $expenseCategory->id,
        'payment_method_id' => $paymentMethod->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => 300,
        'gst_amount' => 0,
        'net_amount' => 300,
    ]);

    $dashboard = app(DashboardMetricsService::class)->getDashboardData();
    $series = $dashboard['monthly_cashflow'];

    expect($series)->toHaveKeys(['labels', 'values', 'money_in', 'money_out'])
        ->and(end($series['money_in']))->toBe(800.0)
        ->and(end($series['money_out']))->toBe(300.0)
        ->and(end($series['values']))->toBe(500.0)
        ->and($dashboard['kpis']['money_in_this_month'])->toBe(800.0)
        ->and($dashboard['kpis']['money_out_this_month'])->toBe(300.0);
});
