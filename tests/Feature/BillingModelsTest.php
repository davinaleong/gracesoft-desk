<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\RetainerPeriod;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

function readyUserForBilling(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

function fixedFeeProject(float $fee = 10000, array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'billing_model' => 'fixed_fee',
        'fixed_fee_total' => $fee,
        'hourly_rate' => 150,
    ], $overrides));
}

function retainerProject(array $overrides = []): Project
{
    return Project::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'billing_model' => 'retainer',
        'retainer_monthly_amount' => 3000,
        'retainer_included_hours' => 10,
        'retainer_overage_rate' => 120,
        'retainer_rollover' => false,
        'hourly_rate' => 100,
    ], $overrides));
}

function logRetainerHours(Project $project, string $date, float $hours): TimeEntry
{
    return TimeEntry::factory()->create([
        'project_id' => $project->id,
        'entry_date' => $date,
        'duration_minutes' => (int) round($hours * 60),
        'is_billable' => true,
    ]);
}

afterEach(function (): void {
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
});

test('existing projects default to hourly and their billable amounts do not change', function () {
    $project = Project::factory()->create(['hourly_rate' => 90]);
    $entry = TimeEntry::factory()->create(['project_id' => $project->id, 'duration_minutes' => 60, 'is_billable' => true]);

    expect($project->fresh()->billing_model)->toBe('hourly')
        ->and($entry->fresh()->billable_amount)->toBe('90.00');

    $this->artisan('desk:recalculate-billable-amounts')->assertSuccessful();

    expect($entry->fresh()->billable_amount)->toBe('90.00');
});

test('milestone amounts cannot exceed the fixed fee', function () {
    $user = readyUserForBilling();
    $project = fixedFeeProject(10000);

    $this->actingAs($user)->post(route('projects.milestones.store', $project), ['name' => 'Discovery', 'amount' => 4000])
        ->assertRedirect(route('projects.show', $project));
    $this->actingAs($user)->post(route('projects.milestones.store', $project), ['name' => 'Build', 'amount' => 6000])
        ->assertRedirect(route('projects.show', $project));
    $this->actingAs($user)->post(route('projects.milestones.store', $project), ['name' => 'Extra', 'amount' => 0.01])
        ->assertSessionHasErrors('amount');

    expect($project->milestones()->count())->toBe(2);

    // Nor can the fee drop below the planned milestones.
    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'billing_model' => 'fixed_fee',
        'fixed_fee_total' => 9000,
    ])->assertSessionHasErrors('fixed_fee_total');

    $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('Discovery');
});

test('a milestone can be invoiced once only, and voiding releases it', function () {
    $user = readyUserForBilling();
    $project = fixedFeeProject(10000);
    $milestone = $project->milestones()->create(['name' => 'Design', 'amount' => 2500, 'status' => 'pending']);

    $this->actingAs($user)->get(route('invoices.create', ['client' => $project->client->uuid]))->assertOk()->assertSee('Design');

    $invoice = app(InvoiceService::class)->createDraft($project->client, [], 'entry', [], null, null, [$milestone->uuid]);

    expect($invoice->total)->toBe('2500.00')
        ->and($milestone->fresh()->status)->toBe('invoiced')
        ->and($invoice->lines()->first()->type)->toBe('milestone');

    expect(fn () => app(InvoiceService::class)->createDraft($project->client, [], 'entry', [], null, null, [$milestone->uuid]))
        ->toThrow(ValidationException::class);

    $this->actingAs($user)->delete(route('projects.milestones.destroy', [$project, $milestone]))->assertSessionHasErrors('milestone');

    app(InvoiceService::class)->void($invoice, 'Wrong amount');

    expect($milestone->fresh()->status)->toBe('pending')
        ->and($milestone->fresh()->invoice_line_id)->toBeNull();
});

test('paying an invoice marks its milestones paid', function () {
    $project = fixedFeeProject(5000);
    $milestone = $project->milestones()->create(['name' => 'Launch', 'amount' => 5000, 'status' => 'pending']);
    $invoice = app(InvoiceService::class)->createDraft($project->client, [], 'entry', [], null, null, [$milestone->uuid]);
    app(InvoiceService::class)->issue($invoice);
    app(InvoiceService::class)->recordPayment($invoice, [
        'account_uuid' => Account::query()->create(['code' => 'B1', 'name' => 'Bank', 'type' => 'bank'])->uuid,
        'payment_date' => now()->toDateString(),
    ]);

    expect($milestone->fresh()->status)->toBe('paid');
});

test('fixed-fee time entries carry no billable amount and are never offered as time lines', function () {
    $project = fixedFeeProject();
    $entry = TimeEntry::factory()->create(['project_id' => $project->id, 'duration_minutes' => 120, 'is_billable' => true]);

    expect($entry->fresh()->billable_amount)->toBe('0.00')
        ->and($entry->fresh()->duration_minutes)->toBe(120)
        ->and(app(InvoiceService::class)->unbilledEntriesFor($project->client)->count())->toBe(0);

    $this->artisan('desk:recalculate-billable-amounts')->assertSuccessful();

    expect($entry->fresh()->billable_amount)->toBe('0.00');
});

test('the retainer draft is generated once per month; re-running creates nothing new', function () {
    $project = retainerProject();
    logRetainerHours($project, '2026-09-10', 4);

    $this->artisan('desk:draft-retainer-invoices', ['--month' => '2026-09'])->expectsOutputToContain('Drafted 1')->assertSuccessful();
    $this->artisan('desk:draft-retainer-invoices', ['--month' => '2026-09'])->expectsOutputToContain('Drafted 0')->assertSuccessful();

    $invoice = Invoice::query()->sole();

    expect(RetainerPeriod::query()->count())->toBe(1)
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->client_id)->toBe($project->client_id)
        ->and($invoice->lines()->count())->toBe(1)
        ->and($invoice->subtotal)->toBe('3000.00');
});

test('overage is (hours used minus included hours) times the overage rate, only when positive', function () {
    $over = retainerProject(['retainer_included_hours' => 10, 'retainer_overage_rate' => 120]);
    logRetainerHours($over, '2026-09-03', 8);
    logRetainerHours($over, '2026-09-20', 4.5);

    $under = retainerProject(['retainer_included_hours' => 10]);
    logRetainerHours($under, '2026-09-03', 9.75);

    $this->artisan('desk:draft-retainer-invoices', ['--month' => '2026-09'])->assertSuccessful();

    $overInvoice = RetainerPeriod::query()->where('project_id', $over->id)->sole()->invoice;
    $overage = $overInvoice->lines()->reorder()->where('sort_order', 2)->sole();

    expect($overage->quantity)->toBe('2.50')
        ->and($overage->amount)->toBe('300.00')
        ->and($overInvoice->subtotal)->toBe('3300.00')
        ->and(RetainerPeriod::query()->where('project_id', $under->id)->sole()->invoice->lines()->count())->toBe(1);
});

test('rollover carries unused hours forward one month only', function () {
    $project = retainerProject(['retainer_included_hours' => 10, 'retainer_rollover' => true]);
    logRetainerHours($project, '2026-07-15', 6);   // 4 unused, carried into August
    logRetainerHours($project, '2026-08-15', 13);  // 14 available, 1 left of the carried hours expires
    logRetainerHours($project, '2026-09-15', 12);  // 0 carried in, 2 over

    foreach (['2026-07', '2026-08', '2026-09'] as $month) {
        $this->artisan('desk:draft-retainer-invoices', ['--month' => $month])->assertSuccessful();
    }

    $periods = RetainerPeriod::query()->where('project_id', $project->id)->orderBy('period')->get()->keyBy('period');

    expect($periods['2026-07']->rollover_out_hours)->toBe('4.00')
        ->and($periods['2026-08']->rollover_in_hours)->toBe('4.00')
        ->and($periods['2026-08']->overage_hours)->toBe('0.00')
        ->and($periods['2026-08']->rollover_out_hours)->toBe('1.00')
        ->and($periods['2026-09']->rollover_in_hours)->toBe('1.00')
        ->and($periods['2026-09']->overage_hours)->toBe('1.00');
});

test('retainer time is locked on the draft and not offered again as hourly time', function () {
    $project = retainerProject();
    $entry = logRetainerHours($project, '2026-09-10', 2);

    expect(app(InvoiceService::class)->unbilledEntriesFor($project->client)->count())->toBe(0);

    $this->artisan('desk:draft-retainer-invoices', ['--month' => '2026-09'])->assertSuccessful();

    expect($entry->fresh()->isInvoiced())->toBeTrue();
});

test('month boundaries use the system timezone', function () {
    config(['app.timezone' => 'Asia/Singapore']);
    date_default_timezone_set('Asia/Singapore');

    // 30 Sep 16:30 UTC is already 1 Oct 00:30 in Singapore, so "last month" is September.
    $this->travelTo(Carbon::parse('2026-09-30 16:30:00', 'UTC'));

    $project = retainerProject();
    logRetainerHours($project, '2026-09-30', 1);

    $this->artisan('desk:draft-retainer-invoices')->expectsOutputToContain('for 2026-09')->assertSuccessful();

    expect(RetainerPeriod::query()->sole()->period)->toBe('2026-09')
        ->and(RetainerPeriod::query()->sole()->hours_used)->toBe('1.00');
});

test('retainer projects without a client are skipped with a warning', function () {
    retainerProject(['client_id' => null, 'code' => 'PRJ-NOCLIENT']);

    $this->artisan('desk:draft-retainer-invoices', ['--month' => '2026-09'])
        ->expectsOutputToContain('PRJ-NOCLIENT: no client, skipped.')
        ->assertSuccessful();

    expect(Invoice::query()->count())->toBe(0);
});

test('the projects report shows revenue by billing model', function () {
    $user = readyUserForBilling();

    $hourly = Project::factory()->create(['client_id' => Client::factory()->create()->id, 'hourly_rate' => 100]);
    TimeEntry::factory()->create(['project_id' => $hourly->id, 'duration_minutes' => 60, 'is_billable' => true]);
    $hourlyInvoice = app(InvoiceService::class)->createDraft($hourly->client, TimeEntry::query()->pluck('uuid')->all(), 'entry', [], null, null);
    app(InvoiceService::class)->issue($hourlyInvoice);

    $fixed = fixedFeeProject(2000);
    $milestone = $fixed->milestones()->create(['name' => 'M1', 'amount' => 2000, 'status' => 'pending']);
    app(InvoiceService::class)->issue(app(InvoiceService::class)->createDraft($fixed->client, [], 'entry', [['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 50]], null, null, [$milestone->uuid]));

    $this->actingAs($user)
        ->get(route('reports.projects', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]))
        ->assertOk()
        ->assertSee('Invoiced Revenue by Billing Model')
        ->assertViewHas('report', fn (array $report) => $report['revenue_by_billing_model'] === [
            'hourly' => 100.0,
            'fixed_fee' => 2000.0,
            'retainer' => 0.0,
            'unassigned' => 50.0,
        ]);
});

test('billing model fields are saved from the project form', function () {
    $user = readyUserForBilling();
    $project = Project::factory()->create();

    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'billing_model' => 'retainer',
        'retainer_monthly_amount' => '2500',
        'retainer_included_hours' => '12',
        'retainer_overage_rate' => '110',
        'retainer_rollover' => '1',
    ])->assertRedirect(route('projects.show', $project));

    $project->refresh();

    expect($project->billing_model)->toBe('retainer')
        ->and($project->retainer_included_hours)->toBe('12.00')
        ->and($project->retainer_rollover)->toBeTrue();

    $this->actingAs($user)->put(route('projects.update', $project), [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'billing_model' => 'retainer',
    ])->assertSessionHasErrors(['retainer_monthly_amount', 'retainer_included_hours']);
});

test('milestone writes are blocked in archive mode', function () {
    $user = readyUserForBilling();
    $project = fixedFeeProject();
    SystemSetting::upsertValues(['archive_mode' => true]);

    $this->actingAs($user)->post(route('projects.milestones.store', $project), ['name' => 'X', 'amount' => 10])
        ->assertSessionHas('status', 'archive-mode-read-only');

    expect(Milestone::query()->count())->toBe(0);
});
