<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;

function readyUserForReports(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function seedReportData(User $user): void
{
    $project = Project::query()->create([
        'code' => 'PRJ-RPT-001',
        'name' => 'Reports Rollout',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $stage = ProjectStage::query()->create([
        'name' => 'Analysis',
        'sort_order' => 2,
        'status' => 'active',
    ]);

    TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 180,
        'is_billable' => true,
        'hourly_rate' => 100,
        'billable_amount' => 300,
    ]);

    $account = Account::query()->create([
        'name' => 'Reports Bank',
        'code' => 'BANK-RPT-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Reports Income',
        'slug' => 'reports-income',
        'type' => 'income',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Reports Transfer',
        'slug' => 'reports-transfer',
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
        'amount' => 900,
        'gst_amount' => 0,
        'net_amount' => 900,
    ]);
}

test('reports pages are accessible and render core sections', function () {
    $user = readyUserForReports();
    seedReportData($user);

    $this->actingAs($user)
        ->get(route('reports.finance'))
        ->assertOk()
        ->assertSee('Finance Report')
        ->assertSee('Expense Breakdown');

    $this->actingAs($user)
        ->get(route('reports.projects'))
        ->assertOk()
        ->assertSee('Project Report')
        ->assertSee('Project Summary');

    $this->actingAs($user)
        ->get(route('reports.monthly-summary'))
        ->assertOk()
        ->assertSee('Monthly Summary')
        ->assertSee('Monthly Ledger');
});

test('report filters are accepted through query string', function () {
    $user = readyUserForReports();
    seedReportData($user);

    $this->actingAs($user)
        ->get(route('reports.finance', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]))
        ->assertOk();
});

test('printable summary routes are accessible', function () {
    $user = readyUserForReports();
    seedReportData($user);

    $this->actingAs($user)
        ->get(route('reports.finance.print'))
        ->assertOk()
        ->assertSee('Finance Report');

    $this->actingAs($user)
        ->get(route('reports.projects.print'))
        ->assertOk()
        ->assertSee('Project Report');

    $this->actingAs($user)
        ->get(route('reports.monthly-summary.print'))
        ->assertOk()
        ->assertSee('Monthly Summary Report');
});

test('reports routes require authentication', function () {
    $this->get(route('reports.finance'))->assertRedirect(route('login'));
    $this->get(route('reports.projects'))->assertRedirect(route('login'));
    $this->get(route('reports.monthly-summary'))->assertRedirect(route('login'));
});
