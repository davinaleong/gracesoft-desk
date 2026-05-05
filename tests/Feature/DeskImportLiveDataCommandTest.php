<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;

function tempCsvPath(string $name): string
{
    $dir = storage_path('app/testing/live-import');

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir.'/'.$name;
}

test('live data import command imports projects time entries and transactions from csv', function () {
    $user = User::factory()->create();

    $project = Project::query()->create([
        'code' => 'PRJ-LIVE-001',
        'name' => 'Existing Live Project',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $stage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'name' => 'Execution',
        'slug' => 'execution',
        'sort_order' => 1,
        'status' => 'active',
    ]);

    $account = Account::query()->create([
        'name' => 'Live Account',
        'code' => 'LIVE-ACC-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Live Expense',
        'slug' => 'live-expense',
        'type' => 'expense',
        'is_active' => true,
    ]);

    $payment = PaymentMethod::query()->create([
        'name' => 'Live Transfer',
        'slug' => 'live-transfer',
        'is_active' => true,
    ]);

    $projectsCsv = tempCsvPath('projects.csv');
    file_put_contents($projectsCsv, implode("\n", [
        'code,name,status,description,starts_on,ends_on,is_billable',
        'PRJ-LIVE-001,Updated Live Project,active,Imported live update,2026-05-01,2026-06-01,yes',
        'PRJ-LIVE-002,New Live Project,paused,Imported new row,2026-05-02,2026-06-02,no',
    ]));

    $timeEntriesCsv = tempCsvPath('time-entries.csv');
    file_put_contents($timeEntriesCsv, implode("\n", [
        'project_uuid,project_code,stage_uuid,stage_name,entry_date,duration_minutes,is_billable,hourly_rate,notes',
        $project->uuid.',,'.$stage->uuid.',Execution,2026-05-05,90,yes,120,Imported live time entry',
    ]));

    $transactionsCsv = tempCsvPath('transactions.csv');
    file_put_contents($transactionsCsv, implode("\n", [
        'transaction_code,account_uuid,account_code,category_uuid,category_slug,payment_method_uuid,payment_method_slug,project_uuid,project_code,type,direction,status,transaction_date,reference,description,amount,gst_amount',
        'TRX-LIVE-001,'.$account->uuid.',,'.$category->uuid.',,'.$payment->uuid.',,'.$project->uuid.',,expense,out,completed,2026-05-06,LIVE-REF,Imported live transaction,220,20',
    ]));

    $this->artisan('desk:import-live-data', [
        '--projects' => $projectsCsv,
        '--time-entries' => $timeEntriesCsv,
        '--transactions' => $transactionsCsv,
    ])->expectsOutputToContain('Live CSV import completed')
        ->assertExitCode(0);

    expect(Project::query()->where('code', 'PRJ-LIVE-001')->value('name'))->toBe('Updated Live Project')
        ->and(Project::query()->where('code', 'PRJ-LIVE-002')->exists())->toBeTrue()
        ->and(TimeEntry::query()->where('notes', 'Imported live time entry')->exists())->toBeTrue()
        ->and(Transaction::query()->where('transaction_code', 'TRX-LIVE-001')->exists())->toBeTrue()
        ->and(TimeEntry::query()->where('notes', 'Imported live time entry')->value('user_id'))->toBe($user->id);
});
