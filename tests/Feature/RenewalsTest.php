<?php

use App\Mail\RenewalReminderMail;
use App\Models\Account;
use App\Models\Category;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Support\RenewalSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

function readyUserForRenewals(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function renewingService(array $overrides = []): Service
{
    $date = $overrides['next_renewal_date'] ?? now()->toDateString();

    return Service::factory()->create(array_merge([
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'expected_amount' => 49.90,
        'currency' => 'SGD',
        'next_renewal_date' => $date,
        'renewal_anchor_day' => CarbonImmutable::parse($date)->day,
        'account_id' => Account::query()->firstOrCreate(['code' => 'BANK-R'], ['name' => 'Ops Bank', 'type' => 'bank'])->id,
        'transaction_category_id' => TransactionCategory::query()->firstOrCreate(['slug' => 'software-renewals'], ['name' => 'Software renewals', 'type' => 'expense'])->id,
        'auto_create_expense' => true,
        'reminder_days_before' => 7,
    ], $overrides));
}

beforeEach(function (): void {
    Mail::fake();
});

test('a monthly service renewing on the 31st moves to 28 or 29 february, then back to the 31st', function () {
    $jan = CarbonImmutable::parse('2027-01-31');
    $feb = RenewalSchedule::next($jan, 'monthly', 31);
    $mar = RenewalSchedule::next($feb, 'monthly', 31);

    expect($feb->toDateString())->toBe('2027-02-28')
        ->and($mar->toDateString())->toBe('2027-03-31')
        ->and(RenewalSchedule::next(CarbonImmutable::parse('2028-01-31'), 'monthly', 31)->toDateString())->toBe('2028-02-29')
        ->and(RenewalSchedule::next(CarbonImmutable::parse('2027-03-31'), 'monthly', 31)->toDateString())->toBe('2027-04-30');
});

test('a yearly renewal on 29 february lands on 28 february in non-leap years', function () {
    $leap = CarbonImmutable::parse('2028-02-29');
    $next = RenewalSchedule::next($leap, 'yearly', 29);

    expect($next->toDateString())->toBe('2029-02-28')
        ->and(RenewalSchedule::next(CarbonImmutable::parse('2031-02-28'), 'yearly', 29)->toDateString())->toBe('2032-02-29');
});

test('the processor keeps the anchor day across short months', function () {
    $this->travelTo('2027-01-31 08:00:00');
    $service = renewingService(['next_renewal_date' => '2027-01-31', 'renewal_anchor_day' => 31]);

    $this->artisan('desk:process-renewals')->assertSuccessful();
    expect($service->fresh()->next_renewal_date->toDateString())->toBe('2027-02-28');

    $this->travelTo('2027-02-28 08:00:00');
    $this->artisan('desk:process-renewals')->assertSuccessful();
    expect($service->fresh()->next_renewal_date->toDateString())->toBe('2027-03-31');
});

test('running the command twice on the same day creates one transaction', function () {
    $service = renewingService();

    $this->artisan('desk:process-renewals')->expectsOutputToContain('Created 1 pending expense')->assertSuccessful();
    $this->artisan('desk:process-renewals')->expectsOutputToContain('Created 0 pending expense')->assertSuccessful();

    expect(Transaction::query()->where('service_id', $service->id)->count())->toBe(1);
});

test('paused and cancelled services never generate anything', function () {
    $paused = renewingService(['status' => 'paused', 'reminder_days_before' => 30]);
    $cancelled = renewingService(['status' => 'cancelled', 'reminder_days_before' => 30]);
    readyUserForRenewals();

    $this->artisan('desk:process-renewals')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and($paused->fresh()->next_renewal_date->toDateString())->toBe(now()->toDateString())
        ->and($cancelled->fresh()->next_renewal_date->toDateString())->toBe(now()->toDateString());
    Mail::assertNothingSent();
});

test('generated transactions are expense, out, pending, linked to the service, with its category', function () {
    $service = renewingService();

    $this->artisan('desk:process-renewals')->assertSuccessful();

    $transaction = Transaction::query()->sole();

    expect($transaction->type)->toBe('expense')
        ->and($transaction->direction)->toBe('out')
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->service_id)->toBe($service->id)
        ->and($transaction->transaction_category_id)->toBe($service->transaction_category_id)
        ->and($transaction->account_id)->toBe($service->account_id)
        ->and($transaction->amount)->toBe('49.90')
        ->and($transaction->transaction_date->toDateString())->toBe(now()->toDateString())
        ->and($service->fresh()->next_renewal_date->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

test('services without the toggle only advance their date', function () {
    $service = renewingService(['auto_create_expense' => false]);

    $this->artisan('desk:process-renewals')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and($service->fresh()->next_renewal_date->isFuture())->toBeTrue();
});

test('each renewal sends one reminder', function () {
    $user = readyUserForRenewals();
    $service = renewingService(['next_renewal_date' => now()->addDays(5)->toDateString(), 'reminder_days_before' => 7]);
    renewingService(['next_renewal_date' => now()->addDays(20)->toDateString(), 'reminder_days_before' => 7]);

    $this->artisan('desk:process-renewals')->assertSuccessful();
    $this->artisan('desk:process-renewals')->assertSuccessful();
    $this->travel(1)->days();
    $this->artisan('desk:process-renewals')->assertSuccessful();

    Mail::assertSent(RenewalReminderMail::class, 1);
    Mail::assertSent(RenewalReminderMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->service->is($service));
    expect($service->fresh()->last_reminded_for->toDateString())->toBe($service->next_renewal_date->toDateString());
});

test('the dashboard lists renewals in the next 30 days', function () {
    $user = readyUserForRenewals();
    renewingService(['name' => 'Soon Service', 'next_renewal_date' => now()->addDays(10)->toDateString()]);
    renewingService(['name' => 'Later Service', 'next_renewal_date' => now()->addDays(45)->toDateString()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Renewals in the Next 30 Days')
        ->assertViewHas('upcomingRenewals', fn ($rows) => $rows->pluck('name')->all() === ['Soon Service']);
});

test('spend report totals equal the sum of completed transactions linked to services', function () {
    $user = readyUserForRenewals();
    $aws = Vendor::factory()->create(['name' => 'AWS']);
    $figma = Vendor::factory()->create(['name' => 'Figma']);
    $cloud = Category::factory()->service()->create(['name' => 'Spend Test Cloud']);
    $design = Category::factory()->service()->create(['name' => 'Spend Test Design']);
    $s1 = renewingService(['vendor_id' => $aws->id, 'category_id' => $cloud->id]);
    $s2 = renewingService(['vendor_id' => $figma->id, 'category_id' => $design->id]);
    $account = Account::query()->where('code', 'BANK-R')->first();

    $make = fn (Service $service, string $amount, string $status) => Transaction::query()->create([
        'account_id' => $account->id, 'service_id' => $service->id, 'type' => 'expense', 'direction' => 'out',
        'status' => $status, 'transaction_date' => now()->toDateString(), 'amount' => $amount, 'gst_amount' => 0,
    ]);
    $make($s1, '120.00', 'completed');
    $make($s1, '80.00', 'completed');
    $make($s2, '45.00', 'completed');
    $make($s2, '999.00', 'pending');
    Transaction::query()->create([
        'account_id' => $account->id, 'type' => 'expense', 'direction' => 'out', 'status' => 'completed',
        'transaction_date' => now()->toDateString(), 'amount' => '500.00', 'gst_amount' => 0,
    ]);

    $expected = (float) Transaction::query()->completed()->whereNotNull('service_id')->sum('amount');

    $this->actingAs($user)->get(route('reports.spend'))
        ->assertOk()
        ->assertViewHas('report', fn (array $report) => $report['total'] === 245.0
            && $report['total'] === $expected
            && $report['by_vendor'] === [
                ['vendor' => 'AWS', 'total' => 200.0, 'count' => 2],
                ['vendor' => 'Figma', 'total' => 45.0, 'count' => 1],
            ]
            && array_sum(array_column($report['by_category'], 'total')) === 245.0);

    $csv = $this->actingAs($user)->get(route('reports.spend.export'))->streamedContent();
    expect($csv)->toContain('AWS,2,200.00')->toContain('Total,,245.00');
});

test('renewal fields are saved from the service form with the anchor day', function () {
    $user = readyUserForRenewals();
    $vendor = Vendor::factory()->create();
    $account = Account::query()->create(['code' => 'BANK-F', 'name' => 'Form Bank', 'type' => 'bank']);

    $this->actingAs($user)->get(route('services.create'))->assertOk();

    $this->actingAs($user)->post(route('services.store'), [
        'vendor_uuid' => $vendor->uuid,
        'name' => 'Hosting',
        'category_id' => Category::factory()->service()->create()->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'expected_amount' => '30',
        'currency' => 'SGD',
        'next_renewal_date' => '2027-01-31',
        'account_uuid' => $account->uuid,
        'auto_create_expense' => '1',
        'reminder_days_before' => 3,
    ])->assertSessionHasNoErrors();

    $service = Service::query()->where('name', 'Hosting')->sole();

    expect($service->renewal_anchor_day)->toBe(31)
        ->and($service->account_id)->toBe($account->id)
        ->and($service->auto_create_expense)->toBeTrue();

    $this->actingAs($user)->get(route('services.show', $service))->assertOk()->assertSee('Monthly');
});
