<?php

use App\Models\Account;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\MarketingDemoSeeder;

use function Pest\Laravel\seed;

test('marketing demo seeder seeds realistic linked and repeatable data', function () {
    seed(MarketingDemoSeeder::class);

    expect(User::count())->toBe(1);
    expect(Project::where('code', 'like', 'DEMO-%')->count())->toBe(5);
    expect(TimeEntry::where('notes', 'like', 'Demo Seed:%')->count())->toBeGreaterThan(40);
    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->count())->toBe(12);

    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->where('type', 'income')->exists())->toBeTrue();
    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->where('type', 'expense')->exists())->toBeTrue();
    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->where('status', 'pending')->exists())->toBeTrue();
    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')
        ->where('project_id', '!=', null)
        ->where('type', 'income')
        ->exists())->toBeTrue();

    expect(Account::where('code', 'BANK-OPERATING')->value('current_balance'))
        ->not()
        ->toBeNull();

    $demoEntryCount = TimeEntry::where('notes', 'like', 'Demo Seed:%')->count();
    $demoTransactionCount = Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->count();

    seed(MarketingDemoSeeder::class);

    expect(TimeEntry::where('notes', 'like', 'Demo Seed:%')->count())->toBe($demoEntryCount);
    expect(Transaction::where('transaction_code', 'like', 'DEMO-TRX-%')->count())->toBe($demoTransactionCount);
});
