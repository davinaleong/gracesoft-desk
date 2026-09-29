<?php

use App\Mail\InvoiceMail;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\InvoiceService;
use App\Services\InvoiceSettings;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function readyUserForInvoicing(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @return array{client: Client, project: Project, stage: ProjectStage}
 */
function invoicingFixture(float $rate = 100.0): array
{
    $client = Client::factory()->create([
        'name' => 'Northwind Retail',
        'address' => "88 Market Street\nSingapore 048948",
        'billing_email' => 'ap@northwind.example',
    ]);
    $project = Project::factory()->create(['client_id' => $client->id, 'hourly_rate' => $rate, 'name' => 'Checkout Revamp']);
    $stage = ProjectStage::query()->create(['name' => 'Development', 'sort_order' => 4, 'status' => 'active']);

    return compact('client', 'project', 'stage');
}

function billableEntry(Project $project, int $minutes = 60, array $overrides = []): TimeEntry
{
    return TimeEntry::factory()->create(array_merge([
        'project_id' => $project->id,
        'duration_minutes' => $minutes,
        'is_billable' => true,
    ], $overrides));
}

function gstRegistered(string $rate = '9'): void
{
    SystemSetting::upsertValues([
        'company_name' => 'GraceSoft Pte Ltd',
        'company_address' => '1 Raffles Place, Singapore 048616',
        'gst_registration_number' => 'M90312345A',
        'gst_rate' => $rate,
    ]);
}

function paymentAccount(): Account
{
    return Account::query()->create(['code' => 'BANK-OPS', 'name' => 'Operating Account', 'type' => 'bank']);
}

/**
 * @param  array<int, TimeEntry>  $entries
 */
function draftFor(Client $client, array $entries, string $grouping = 'entry', array $manualLines = []): Invoice
{
    return app(InvoiceService::class)->createDraft($client, array_map(fn ($e) => $e->uuid, $entries), $grouping, $manualLines, null, null);
}

test('invoice numbers are sequential per year with no gaps and drafts never consume a number', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();

    $first = draftFor($client, [billableEntry($project)]);
    $deleted = draftFor($client, [billableEntry($project)]);
    $second = draftFor($client, [billableEntry($project)]);

    expect($first->invoice_number)->toBeNull();

    $this->actingAs($user)->delete(route('invoices.destroy', $deleted))->assertRedirect(route('invoices.index'));
    $this->actingAs($user)->post(route('invoices.issue', $first));
    $this->actingAs($user)->post(route('invoices.issue', $second));

    $year = now()->year;
    expect($first->fresh()->invoice_number)->toBe("INV-{$year}-00001")
        ->and($second->fresh()->invoice_number)->toBe("INV-{$year}-00002")
        ->and(Invoice::withTrashed()->find($deleted->id)->invoice_number)->toBeNull();

    $this->travelTo(now()->addYear()->startOfYear()->addDay());
    $nextYear = draftFor($client, [billableEntry($project)]);
    app(InvoiceService::class)->issue($nextYear);

    expect($nextYear->fresh()->invoice_number)->toBe('INV-'.($year + 1).'-00001');
});

test('a failed issue returns its number to the sequence', function () {
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $empty = Invoice::factory()->create(['client_id' => $client->id]);

    expect(fn () => app(InvoiceService::class)->issue($empty))->toThrow(ValidationException::class);

    $invoice = draftFor($client, [billableEntry($project)]);
    app(InvoiceService::class)->issue($invoice);

    expect($invoice->fresh()->invoice_number)->toBe('INV-'.now()->year.'-00001');
});

test('only billable, unbilled, non-deleted entries of the client are offered', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();

    $offered = billableEntry($project, 60, ['notes' => 'offered-entry']);
    $nonBillable = billableEntry($project, 60, ['is_billable' => false, 'notes' => 'non-billable-entry']);
    $deleted = billableEntry($project, 60, ['notes' => 'deleted-entry']);
    $deleted->delete();
    $billed = billableEntry($project, 60, ['notes' => 'billed-entry']);
    draftFor($client, [$billed]);
    $otherClient = billableEntry(Project::factory()->create(['client_id' => Client::factory()->create()->id]), 60, ['notes' => 'other-client-entry']);

    $this->actingAs($user)->get(route('invoices.create', ['client' => $client->uuid]))
        ->assertOk()
        ->assertSee('offered-entry')
        ->assertDontSee('non-billable-entry')
        ->assertDontSee('deleted-entry')
        ->assertDontSee('billed-entry')
        ->assertDontSee('other-client-entry');

    foreach ([$nonBillable, $deleted, $billed, $otherClient] as $entry) {
        $this->actingAs($user)->post(route('invoices.store'), [
            'client_uuid' => $client->uuid,
            'grouping' => 'entry',
            'time_entry_uuids' => [$entry->uuid],
        ])->assertSessionHasErrors('time_entry_uuids');
    }

    expect(Invoice::query()->count())->toBe(1);

    $this->actingAs($user)->post(route('invoices.store'), [
        'client_uuid' => $client->uuid,
        'grouping' => 'entry',
        'time_entry_uuids' => [$offered->uuid],
    ])->assertSessionHasNoErrors();

    expect($offered->fresh()->isInvoiced())->toBeTrue();
});

test('three 20-minute entries round consistently and lines always sum to the total', function (string $grouping, int $expectedLines) {
    gstRegistered('9');
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);

    $entries = [billableEntry($project, 20), billableEntry($project, 20), billableEntry($project, 20)];
    $invoice = draftFor($client, $entries, $grouping, [
        ['description' => 'Hosting setup', 'quantity' => '1.5', 'unit_price' => '33.33'],
    ]);
    $invoice->load('lines');

    expect($invoice->lines)->toHaveCount($expectedLines + 1)
        ->and($invoice->lines->last()->amount)->toBe('50.00') // 1.5 × 33.33 = 49.995 → 50.00
        ->and($invoice->subtotal)->toBe('149.99')             // 3 × 33.33 + 50.00
        ->and($invoice->total)->toBe(number_format((float) $invoice->subtotal + (float) $invoice->gst_amount, 2, '.', ''));

    $lineCents = $invoice->lines->sum(fn ($line) => (int) round(((float) $line->amount + (float) $line->gst_amount) * 100));
    expect($lineCents)->toBe((int) round((float) $invoice->total * 100));
})->with([
    'one line per entry' => ['entry', 3],
    'grouped by stage' => ['stage', 1],
]);

test('grouped lines carry the summed hours and amounts', function () {
    ['client' => $client, 'project' => $project, 'stage' => $stage] = invoicingFixture(100.0);

    $entries = [
        billableEntry($project, 20, ['project_stage_id' => $stage->id]),
        billableEntry($project, 20, ['project_stage_id' => $stage->id]),
        billableEntry($project, 20, ['project_stage_id' => $stage->id]),
    ];
    $line = draftFor($client, $entries, 'stage')->lines()->first();

    expect($line->quantity)->toBe('1.00')
        ->and($line->amount)->toBe('99.99')
        ->and($line->description)->toContain('Development')
        ->and($line->description)->toContain('3 entries');
});

test('issued invoices cannot be edited or deleted', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)]);
    app(InvoiceService::class)->issue($invoice);

    $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertRedirect(route('invoices.show', $invoice));

    $this->actingAs($user)->from(route('invoices.show', $invoice))->put(route('invoices.update', $invoice), [
        'manual_lines' => [['description' => 'Sneaky', 'quantity' => 1, 'unit_price' => 999]],
    ])->assertSessionHasErrors('invoice');

    $this->actingAs($user)->delete(route('invoices.destroy', $invoice))->assertSessionHasErrors('invoice');

    expect($invoice->fresh()->lines()->count())->toBe(1)
        ->and(Invoice::query()->find($invoice->id))->not->toBeNull();
});

test('draft invoices can be edited: manual lines replaced and time lines released', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $keep = billableEntry($project, 60);
    $release = billableEntry($project, 30);
    $invoice = draftFor($client, [$keep, $release], 'entry', [['description' => 'Old fee', 'quantity' => 1, 'unit_price' => 10]]);
    $releaseLine = $release->fresh()->invoiceLine;

    $this->actingAs($user)->put(route('invoices.update', $invoice), [
        'remove_line_uuids' => [$releaseLine->uuid],
        'manual_lines' => [['description' => 'New fee', 'quantity' => 2, 'unit_price' => 25]],
        'notes' => 'Thanks!',
    ])->assertRedirect(route('invoices.show', $invoice));

    $invoice->refresh()->load('lines');

    expect($invoice->lines->pluck('description')->all())->toContain('New fee')
        ->and($invoice->lines->pluck('description')->all())->not->toContain('Old fee')
        ->and($invoice->subtotal)->toBe('150.00')
        ->and($invoice->notes)->toBe('Thanks!')
        ->and($release->fresh()->isInvoiced())->toBeFalse()
        ->and($keep->fresh()->isInvoiced())->toBeTrue();
});

test('invoiced time entries cannot be edited or deleted until the invoice is voided', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $entry = billableEntry($project, 60, ['notes' => 'original']);
    $invoice = draftFor($client, [$entry]);
    app(InvoiceService::class)->issue($invoice);

    $this->actingAs($user)->get(route('time-entries.edit', $entry))->assertRedirect(route('time-entries.show', $entry));
    $this->actingAs($user)->put(route('time-entries.update', $entry), [
        'project_uuid' => $project->uuid,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => 600,
        'is_billable' => true,
        'notes' => 'tampered',
    ])->assertRedirect(route('time-entries.show', $entry));
    $this->actingAs($user)->delete(route('time-entries.destroy', $entry))->assertRedirect(route('time-entries.show', $entry));

    expect($entry->fresh()->notes)->toBe('original')
        ->and($entry->fresh()->trashed())->toBeFalse();

    expect(fn () => $entry->fresh()->update(['duration_minutes' => 5]))->toThrow(ValidationException::class);

    $this->actingAs($user)->post(route('invoices.void', $invoice), ['void_reason' => 'Wrong client'])
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->status)->toBe('void')
        ->and($invoice->fresh()->invoice_number)->not->toBeNull()
        ->and($invoice->fresh()->void_reason)->toBe('Wrong client')
        ->and($entry->fresh()->isInvoiced())->toBeFalse();

    $this->actingAs($user)->get(route('time-entries.edit', $entry))->assertOk();
});

test('invoiced amounts are frozen: rate changes and recalculation do not touch billed entries', function () {
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $entry = billableEntry($project, 60);
    draftFor($client, [$entry]);

    $project->update(['hourly_rate' => 500]);
    $this->artisan('desk:recalculate-billable-amounts')->assertSuccessful();

    expect($entry->fresh()->billable_amount)->toBe('100.00');
});

test('recording payment creates exactly one income transaction even on double submit', function () {
    $user = readyUserForInvoicing();
    gstRegistered('9');
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $invoice = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($invoice);
    $account = paymentAccount();

    $payload = ['account_uuid' => $account->uuid, 'payment_date' => now()->toDateString()];

    $this->actingAs($user)->post(route('invoices.payment', $invoice), $payload)->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
    $this->actingAs($user)->post(route('invoices.payment', $invoice), $payload)->assertRedirect(route('invoices.show', $invoice));

    expect(Transaction::query()->where('invoice_id', $invoice->id)->count())->toBe(1);

    $transaction = Transaction::query()->where('invoice_id', $invoice->id)->first();

    expect($transaction->type)->toBe('income')
        ->and($transaction->direction)->toBe('in')
        ->and($transaction->status)->toBe('completed')
        ->and($transaction->amount)->toBe('109.00')
        ->and($transaction->gst_amount)->toBe('9.00')
        ->and($transaction->net_amount)->toBe('100.00')
        ->and($transaction->project_id)->toBe($project->id)
        ->and($transaction->client_id)->toBe($client->id)
        ->and($invoice->fresh()->status)->toBe('paid');
});

test('draft and void invoices cannot be marked paid', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)]);
    $account = paymentAccount();

    $this->actingAs($user)->post(route('invoices.payment', $invoice), [
        'account_uuid' => $account->uuid,
        'payment_date' => now()->toDateString(),
    ])->assertSessionHasErrors('invoice');

    expect(Transaction::query()->count())->toBe(0);
});

test('with gst registration the invoice shows the fields iras requires on a tax invoice', function () {
    gstRegistered('9');
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $invoice = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($invoice);
    $invoice->refresh()->load(['client', 'lines']);

    $html = view('invoices.pdf', ['invoice' => $invoice, 'settings' => app(InvoiceSettings::class)])->render();

    expect($html)
        ->toContain('Tax Invoice')
        ->toContain('GraceSoft Pte Ltd')                    // supplier name
        ->toContain('1 Raffles Place, Singapore 048616')    // supplier address
        ->toContain('GST Reg. No.: M90312345A')             // supplier GST registration number
        ->toContain($invoice->invoice_number)               // invoice number
        ->toContain('Date of Issue: '.now()->format('d M Y'))
        ->toContain('Northwind Retail')                     // customer name
        ->toContain('88 Market Street')                     // customer address
        ->toContain('Checkout Revamp')                      // description
        ->toContain('Total excl. GST')
        ->toContain('GST @ 9%')
        ->toContain('9.00')
        ->toContain('Total incl. GST')
        ->toContain('109.00');
});

test('without gst registration no gst appears on the invoice', function () {
    SystemSetting::upsertValues(['gst_rate' => '9', 'gst_registration_number' => '']);
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $invoice = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($invoice);
    $invoice->refresh()->load(['client', 'lines']);

    $html = view('invoices.pdf', ['invoice' => $invoice, 'settings' => app(InvoiceSettings::class)])->render();

    expect($invoice->gst_amount)->toBe('0.00')
        ->and($invoice->total)->toBe('100.00')
        ->and($html)->not->toContain('GST')
        ->and($html)->not->toContain('Tax Invoice');
});

test('the pdf is stored on s3 as a document and served only through a signed url', function () {
    Storage::fake('s3');
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)]);

    $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertRedirect(route('invoices.show', $invoice));

    $this->actingAs($user)->post(route('invoices.issue', $invoice));
    $invoice->refresh();
    $document = $invoice->pdfDocument;

    expect($document)->not->toBeNull()
        ->and($document->documentable_type)->toBe(Invoice::class)
        ->and($document->documentable_id)->toBe($invoice->id)
        ->and($document->name)->toBe($invoice->invoice_number.'.pdf');
    Storage::disk('s3')->assertExists($document->path);
    expect(Storage::disk('s3')->get($document->path))->toStartWith('%PDF');

    $location = $this->actingAs($user)->get(route('invoices.pdf', $invoice))->assertRedirect()->headers->get('Location');

    expect($location)->toContain('expiration=');
});

test('sending emails the client with the pdf attached and records sent_at', function () {
    Storage::fake('s3');
    Mail::fake();
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)]);
    $this->actingAs($user)->post(route('invoices.issue', $invoice));

    $this->actingAs($user)->post(route('invoices.send', $invoice))->assertRedirect(route('invoices.show', $invoice));

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($client, $invoice): bool {
        return $mail->hasTo($client->billing_email)
            && $mail->hasAttachment(
                Attachment::fromStorageDisk('s3', $invoice->fresh()->pdfDocument->path)
                    ->as($invoice->fresh()->invoice_number.'.pdf')
                    ->withMime('application/pdf')
            );
    });

    expect($invoice->fresh()->sent_at)->not->toBeNull();
});

test('drafts cannot be sent', function () {
    Mail::fake();
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)]);

    $this->actingAs($user)->post(route('invoices.send', $invoice))->assertSessionHas('error');

    Mail::assertNothingSent();
});

test('create, issue, payment and void are audit-logged', function () {
    Storage::fake('s3');
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();

    $this->actingAs($user)->post(route('invoices.store'), [
        'client_uuid' => $client->uuid,
        'grouping' => 'entry',
        'time_entry_uuids' => [billableEntry($project)->uuid],
    ]);
    $paid = Invoice::query()->latest('id')->first();
    $this->actingAs($user)->post(route('invoices.issue', $paid));
    $this->actingAs($user)->post(route('invoices.payment', $paid), ['account_uuid' => paymentAccount()->uuid, 'payment_date' => now()->toDateString()]);

    $voided = draftFor($client, [billableEntry($project)]);
    $this->actingAs($user)->post(route('invoices.void', $voided), ['void_reason' => 'Duplicate']);

    $paidLogs = AuditLog::query()->where('auditable_type', Invoice::class)->where('auditable_id', $paid->id)->get();

    expect($paidLogs->where('action', 'created'))->toHaveCount(1)
        ->and($paidLogs->first(fn ($log) => ($log->new_values['status'] ?? null) === 'issued'))->not->toBeNull()
        ->and($paidLogs->first(fn ($log) => ($log->new_values['status'] ?? null) === 'paid')?->old_values['status'])->toBe('issued')
        ->and(AuditLog::query()->where('auditable_type', Invoice::class)->where('auditable_id', $voided->id)->get()
            ->first(fn ($log) => ($log->new_values['status'] ?? null) === 'void'))->not->toBeNull()
        ->and(AuditLog::query()->where('auditable_type', Transaction::class)->where('action', 'created')->count())->toBe(1);
});

test('all invoice writes are blocked in archive mode', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $entry = billableEntry($project);
    $draft = draftFor($client, [billableEntry($project)]);
    SystemSetting::upsertValues(['archive_mode' => true]);

    $requests = [
        ['post', route('invoices.store'), ['client_uuid' => $client->uuid, 'grouping' => 'entry', 'time_entry_uuids' => [$entry->uuid]]],
        ['put', route('invoices.update', $draft), ['notes' => 'x']],
        ['delete', route('invoices.destroy', $draft), []],
        ['post', route('invoices.issue', $draft), []],
        ['post', route('invoices.send', $draft), []],
        ['post', route('invoices.payment', $draft), ['account_uuid' => paymentAccount()->uuid, 'payment_date' => now()->toDateString()]],
        ['post', route('invoices.void', $draft), ['void_reason' => 'x']],
    ];

    foreach ($requests as [$method, $url, $data]) {
        $this->actingAs($user)->{$method}($url, $data)->assertSessionHas('status', 'archive-mode-read-only');
    }

    expect(Invoice::query()->count())->toBe(1)
        ->and($draft->fresh()->status)->toBe('draft')
        ->and($draft->fresh()->notes)->toBeNull();

    $this->actingAs($user)->get(route('invoices.show', $draft))->assertOk();
});

test('paying an invoice invalidates the dashboard cache and shows outstanding receivables', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $invoice = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($invoice);

    $metrics = app(DashboardMetricsService::class);
    expect($metrics->getDashboardData()['kpis']['outstanding_receivables'])->toBe(100.0);
    $keyBeforePayment = $metrics->cacheKey();

    $this->travel(1)->seconds();
    app(InvoiceService::class)->recordPayment($invoice, ['account_uuid' => paymentAccount()->uuid, 'payment_date' => now()->toDateString()]);

    expect($metrics->cacheKey())->not->toBe($keyBeforePayment)
        ->and($metrics->getDashboardData()['kpis']['outstanding_receivables'])->toBe(0.0);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Outstanding Receivables');
});

test('finance report and csv export include invoiced, paid and outstanding totals', function () {
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);

    $paid = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($paid);
    app(InvoiceService::class)->recordPayment($paid, ['account_uuid' => paymentAccount()->uuid, 'payment_date' => now()->toDateString()]);

    $outstanding = draftFor($client, [billableEntry($project, 30)]);
    app(InvoiceService::class)->issue($outstanding);

    $range = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()];

    $this->actingAs($user)->get(route('reports.finance', $range))
        ->assertOk()
        ->assertSee('Invoiced')
        ->assertSee('Outstanding (Now)');

    $csv = $this->actingAs($user)->get(route('reports.finance.export', $range))->streamedContent();

    expect($csv)->toContain('"Invoiced (issued in range)",150.00')
        ->and($csv)->toContain('"Invoices paid (in range)",100.00')
        ->and($csv)->toContain('"Outstanding receivables (now)",50.00');
});

test('bot invoices summary requires a sanctum token and returns receivables', function () {
    ['client' => $client, 'project' => $project] = invoicingFixture(100.0);
    $invoice = draftFor($client, [billableEntry($project, 60)]);
    app(InvoiceService::class)->issue($invoice, now()->subDays(40));

    $this->getJson('/api/bot/invoices/summary')->assertUnauthorized();

    $token = User::factory()->create()->createToken('bot')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/bot/invoices/summary')
        ->assertOk()
        ->assertJsonPath('outstanding', 100)
        ->assertJsonPath('outstanding_count', 1)
        ->assertJsonPath('overdue', 100)
        ->assertJsonPath('overdue_invoices.0.number', $invoice->fresh()->invoice_number)
        ->assertJsonPath('overdue_invoices.0.client', 'Northwind Retail');
});

test('invoice pages render', function () {
    Storage::fake('s3');
    $user = readyUserForInvoicing();
    ['client' => $client, 'project' => $project] = invoicingFixture();
    $invoice = draftFor($client, [billableEntry($project)], 'entry', [['description' => 'Setup fee', 'quantity' => 1, 'unit_price' => 50]]);

    $this->actingAs($user)->get(route('invoices.index'))->assertOk()->assertSee('Draft');
    $this->actingAs($user)->get(route('invoices.create'))->assertOk();
    $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Setup fee');
    $this->actingAs($user)->get(route('invoices.edit', $invoice))->assertOk();
    $this->actingAs($user)->get(route('clients.show', $client))->assertOk()->assertSee('Invoices');

    $this->actingAs($user)->get('/invoices/'.$invoice->id)->assertNotFound();
});
