<?php

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function readyUserForTransactionsImport(): User
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
function transactionImportDependencies(): array
{
    $account = Account::query()->create([
        'name' => 'Import Account',
        'code' => 'BANK-IMP-001',
        'type' => 'bank',
        'currency' => 'SGD',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $category = TransactionCategory::query()->create([
        'name' => 'Import Expense',
        'slug' => 'import-expense',
        'type' => 'expense',
        'is_active' => true,
    ]);

    $paymentMethod = PaymentMethod::query()->create([
        'name' => 'Import Bank Transfer',
        'slug' => 'import-bank-transfer',
        'is_active' => true,
    ]);

    $project = Project::query()->create([
        'code' => 'PRJ-IMP-TRX',
        'name' => 'Import Transactions Project',
        'status' => 'active',
        'is_billable' => false,
    ]);

    return compact('account', 'category', 'paymentMethod', 'project');
}

test('transactions import page is accessible', function () {
    $user = readyUserForTransactionsImport();

    $this->actingAs($user)
        ->get(route('transactions.import.create'))
        ->assertOk()
        ->assertSee('Import Transactions CSV');
});

test('transactions import template csv can be downloaded', function () {
    $user = readyUserForTransactionsImport();

    $this->actingAs($user)
        ->get(route('transactions.import.template'))
        ->assertOk()
        ->assertDownload('transactions-import-template.csv')
        ->assertStreamedContent("transaction_code,account_uuid,account_code,category_uuid,category_slug,payment_method_uuid,payment_method_slug,project_uuid,project_code,type,direction,status,transaction_date,reference,description,amount,gst_amount\nTRX-001,,BANK-001,,software,,bank-transfer,,PRJ-001,expense,out,completed,2026-07-20,INV-2026-001,\"Subscription renewal\",240.00,20.00\n");
});

test('transactions csv can be previewed and committed with transaction code upsert and uuid mapping', function () {
    $user = readyUserForTransactionsImport();
    $dependencies = transactionImportDependencies();

    Transaction::query()->create([
        'transaction_code' => 'TRX-IMPORT-001',
        'account_id' => $dependencies['account']->id,
        'transaction_category_id' => $dependencies['category']->id,
        'payment_method_id' => $dependencies['paymentMethod']->id,
        'project_id' => $dependencies['project']->id,
        'type' => 'expense',
        'direction' => 'out',
        'status' => 'pending',
        'transaction_date' => '2026-05-01',
        'reference' => 'OLD-REF',
        'description' => 'Old import row',
        'amount' => 100,
        'gst_amount' => 5,
        'net_amount' => 95,
    ]);

    $csvContent = implode("\n", [
        'id,uuid,transaction_code,account_uuid,account_code,category_uuid,category_slug,payment_method_uuid,payment_method_slug,project_uuid,project_code,type,direction,status,transaction_date,reference,description,amount,gst_amount',
        '11,aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa,trx-import-001,'.$dependencies['account']->uuid.',WRONG-CODE,'.$dependencies['category']->uuid.',wrong-slug,'.$dependencies['paymentMethod']->uuid.',wrong-pay,'.$dependencies['project']->uuid.',WRONG-PROJECT,expense,out,completed,2026-05-02,NEW-REF,Updated by import,220,20',
        '12,bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb,trx-import-002,,BANK-IMP-001,,import-expense,,import-bank-transfer,,PRJ-IMP-TRX,income,in,completed,2026-05-03,INC-REF,New imported row,450,0',
    ]);

    $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

    $previewResponse = $this->actingAs($user)
        ->post(route('transactions.import.preview'), [
            'csv_file' => $file,
        ]);

    $previewResponse->assertOk()->assertSee('Transactions Import Preview');

    expect(session('transactions_import_rows'))->toBeArray()->toHaveCount(2);

    $commitResponse = $this->actingAs($user)
        ->post(route('transactions.import.commit'));

    $commitResponse->assertRedirect(route('transactions.index'));

    expect(Transaction::query()->count())->toBe(2)
        ->and(Transaction::query()->where('transaction_code', 'TRX-IMPORT-001')->exists())->toBeTrue()
        ->and(Transaction::query()->where('transaction_code', 'TRX-IMPORT-002')->exists())->toBeTrue();

    $updated = Transaction::query()->where('transaction_code', 'TRX-IMPORT-001')->firstOrFail();
    $created = Transaction::query()->where('transaction_code', 'TRX-IMPORT-002')->firstOrFail();

    expect((float) $updated->amount)->toBe(220.0)
        ->and((float) $updated->gst_amount)->toBe(20.0)
        ->and((float) $updated->net_amount)->toBe(200.0)
        ->and($updated->status)->toBe('completed')
        ->and($updated->reference)->toBe('NEW-REF')
        ->and((float) $created->net_amount)->toBe(450.0)
        ->and($created->project_id)->toBe($dependencies['project']->id);
});

test('transactions import preview rejects rows that violate money in and gst integrity rules', function () {
    $user = readyUserForTransactionsImport();
    $dependencies = transactionImportDependencies();

    $csvContent = implode("\n", [
        'account_code,type,direction,status,transaction_date,amount,gst_amount',
        $dependencies['account']->code.',income,out,completed,2026-05-03,100,120',
    ]);

    $file = UploadedFile::fake()->createWithContent('transactions-invalid.csv', $csvContent);

    $previewResponse = $this->actingAs($user)
        ->post(route('transactions.import.preview'), [
            'csv_file' => $file,
        ]);

    $previewResponse->assertOk()
        ->assertSee('For income transactions, please choose the "Money In" direction.')
        ->assertSee('GST cannot be higher than the transaction amount.');

    expect(session('transactions_import_rows'))->toBeArray()->toHaveCount(0);
});

test('transactions import routes require authentication', function () {
    $this->get(route('transactions.import.create'))->assertRedirect(route('login'));
    $this->get(route('transactions.import.template'))->assertRedirect(route('login'));
});
