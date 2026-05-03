<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;

function readyUserForReportExports(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function seedExportData(User $user): void
{
    $project = Project::query()->create([
        'code' => 'PRJ-EXP-001',
        'name' => 'Export Coverage Project',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'name' => 'Execution',
        'slug' => 'execution-export',
        'sort_order' => 1,
        'status' => 'active',
    ]);

    TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'user_id' => $user->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 60,
        'is_billable' => true,
        'hourly_rate' => 120,
        'billable_amount' => 120,
    ]);

    $account = Account::query()->create([
        'name' => 'Export Bank',
        'code' => 'BANK-EXP-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Export Income',
        'slug' => 'export-income',
        'type' => 'income',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Export Transfer',
        'slug' => 'export-transfer',
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
        'amount' => 600,
        'gst_amount' => 0,
        'net_amount' => 600,
    ]);
}

test('finance, project, and monthly reports can be exported as csv', function () {
    $user = readyUserForReportExports();
    seedExportData($user);

    $financeResponse = $this->actingAs($user)
        ->get(route('reports.finance.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=finance-report.csv');

    $projectResponse = $this->actingAs($user)
        ->get(route('reports.projects.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=project-report.csv');

    $monthlyResponse = $this->actingAs($user)
        ->get(route('reports.monthly-summary.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=monthly-summary-report.csv');

    $financeCsv = $financeResponse->streamedContent();
    $projectCsv = $projectResponse->streamedContent();
    $monthlyCsv = $monthlyResponse->streamedContent();

    expect($financeCsv)
        ->toContain('"Transaction Code","Transaction UUID"')
        ->toContain('"Project Code","Project UUID"')
        ->toContain('PRJ-EXP-001')
        ->not->toContain('account_id')
        ->not->toContain('project_id')
        ->not->toContain('transaction_id');

    expect($projectCsv)
        ->toContain('"Project Code","Project UUID","Project Name"')
        ->toContain('PRJ-EXP-001')
        ->not->toContain(',id,')
        ->not->toContain('project_id');

    expect($monthlyCsv)
        ->toContain('Month,Income,Expense,Pending')
        ->not->toContain('_id')
        ->not->toContain(',id,');
});

test('report export routes require authentication', function () {
    $this->get(route('reports.finance.export'))->assertRedirect(route('login'));
    $this->get(route('reports.projects.export'))->assertRedirect(route('login'));
    $this->get(route('reports.monthly-summary.export'))->assertRedirect(route('login'));
});
