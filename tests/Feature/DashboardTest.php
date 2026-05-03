<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;

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
    ]);

    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'name' => 'Execution',
        'slug' => 'execution-stage',
        'sort_order' => 1,
        'status' => 'active',
    ]);

    TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 120,
        'is_billable' => true,
        'hourly_rate' => 100,
        'billable_amount' => 200,
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

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Active Projects')
        ->assertSee('Total Logged Hours')
        ->assertSee('Total Billable Value')
        ->assertSee('Monthly Net Cashflow')
        ->assertSee('PRJ-DB-001');
});

test('dashboard route requires authentication', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
