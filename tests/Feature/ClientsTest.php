<?php

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function readyUserForClients(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Pte Ltd',
        'billing_email' => 'accounts@acme.example',
        'address' => '1 Example Road',
        'tax_id' => '201912345K',
        'currency' => 'sgd',
        'default_hourly_rate' => '120.00',
        'status' => 'active',
        'notes' => null,
    ], $overrides);
}

test('new clients get sequential codes', function () {
    $first = Client::factory()->create();
    $second = Client::factory()->create();

    expect($first->client_code)->toBe('CLT-00001')
        ->and($second->client_code)->toBe('CLT-00002');
});

test('client codes keep counting past soft-deleted clients', function () {
    Client::factory()->create()->delete();

    expect(Client::factory()->create()->client_code)->toBe('CLT-00002');
});

test('client can be created, viewed and updated through the ui', function () {
    $user = readyUserForClients();

    $this->actingAs($user)->get(route('clients.create'))->assertOk();

    $response = $this->actingAs($user)->post(route('clients.store'), clientPayload());

    $client = Client::query()->where('name', 'Acme Pte Ltd')->firstOrFail();
    $response->assertRedirect(route('clients.show', $client));

    expect($client->client_code)->toBe('CLT-00001')
        ->and($client->currency)->toBe('SGD')
        ->and($client->default_hourly_rate)->toBe('120.00');

    $this->actingAs($user)->get(route('clients.index'))->assertOk()->assertSee('CLT-00001');
    $this->actingAs($user)->get(route('clients.show', $client))->assertOk()->assertSee('Acme Pte Ltd');
    $this->actingAs($user)->get(route('clients.edit', $client))->assertOk();

    $this->actingAs($user)
        ->put(route('clients.update', $client), clientPayload(['name' => 'Acme Holdings']))
        ->assertRedirect(route('clients.show', $client));

    expect($client->fresh()->name)->toBe('Acme Holdings');
});

test('client validation rejects bad email, currency and negative rate', function () {
    $user = readyUserForClients();

    $this->actingAs($user)
        ->post(route('clients.store'), clientPayload([
            'billing_email' => 'not-an-email',
            'currency' => 'DOLLARS',
            'default_hourly_rate' => '-1',
        ]))
        ->assertSessionHasErrors(['billing_email', 'currency', 'default_hourly_rate']);

    expect(Client::query()->count())->toBe(0);
});

test('client urls resolve by uuid and a numeric id returns 404', function () {
    $user = readyUserForClients();
    $client = Client::factory()->create();

    $this->actingAs($user)->get('/clients/'.$client->id)->assertNotFound();
    $this->actingAs($user)->get('/clients/'.$client->id.'/edit')->assertNotFound();
    $this->actingAs($user)->get(route('clients.show', $client))->assertOk();

    expect(route('clients.show', $client))->toContain($client->uuid);
});

test('projects link and unlink from a client', function () {
    $user = readyUserForClients();
    $client = Client::factory()->create();
    $project = Project::factory()->create();

    $payload = [
        'code' => $project->code,
        'name' => $project->name,
        'status' => 'active',
        'is_billable' => true,
        'hourly_rate' => '100.00',
    ];

    $this->actingAs($user)
        ->put(route('projects.update', $project), $payload + ['client_uuid' => $client->uuid])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->client_id)->toBe($client->id);

    $this->actingAs($user)->get(route('clients.show', $client))->assertSee($project->code);
    $this->actingAs($user)->get(route('projects.show', $project))->assertSee($client->name);

    $this->actingAs($user)
        ->put(route('projects.update', $project), $payload + ['client_uuid' => ''])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->client_id)->toBeNull();
});

test('projects with no client still load and report', function () {
    $user = readyUserForClients();
    $project = Project::factory()->create(['client_id' => null]);
    TimeEntry::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertSee($project->code);
    $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
    $this->actingAs($user)->get(route('projects.edit', $project))->assertOk();
    $this->actingAs($user)->get(route('reports.projects'))->assertOk()->assertSee($project->code);
});

test('choosing a project on a transaction prefills that project client', function () {
    $user = readyUserForClients();
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $account = Account::query()->create(['code' => 'BANK-T', 'name' => 'Test Bank', 'type' => 'bank']);

    $this->actingAs($user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('data-client-uuid="'.$client->uuid.'"', false);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_uuid' => $account->uuid,
        'project_uuid' => $project->uuid,
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => '100.00',
        'gst_amount' => '0.00',
    ])->assertSessionHasNoErrors();

    expect(Transaction::query()->latest('id')->first()->client_id)->toBe($client->id);
});

test('an explicit client on a transaction wins over the project client', function () {
    $user = readyUserForClients();
    $projectClient = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $projectClient->id]);
    $account = Account::query()->create(['code' => 'BANK-T', 'name' => 'Test Bank', 'type' => 'bank']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'account_uuid' => $account->uuid,
        'project_uuid' => $project->uuid,
        'client_uuid' => $otherClient->uuid,
        'type' => 'income',
        'direction' => 'in',
        'status' => 'completed',
        'transaction_date' => now()->toDateString(),
        'amount' => '100.00',
        'gst_amount' => '0.00',
    ])->assertSessionHasNoErrors();

    expect(Transaction::query()->latest('id')->first()->client_id)->toBe($otherClient->id);
});

test('billable amount follows project rate, then client rate, then system default', function () {
    SystemSetting::upsertValues(['default_hourly_rate' => '50.00']);

    $client = Client::factory()->withRate(80)->create();
    $withOwnRate = Project::factory()->create(['client_id' => $client->id, 'hourly_rate' => 100]);
    $inheritsClient = Project::factory()->create(['client_id' => $client->id, 'hourly_rate' => null]);
    $inheritsSystem = Project::factory()->create(['client_id' => null, 'hourly_rate' => null]);
    $clientWithoutRate = Project::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'hourly_rate' => 0,
    ]);

    $entry = fn (Project $project) => TimeEntry::factory()->create([
        'project_id' => $project->id,
        'duration_minutes' => 90,
        'is_billable' => true,
    ]);

    expect($entry($withOwnRate)->billable_amount)->toBe('150.00')
        ->and($entry($inheritsClient)->billable_amount)->toBe('120.00')
        ->and($entry($inheritsSystem)->billable_amount)->toBe('75.00')
        ->and($entry($clientWithoutRate)->billable_amount)->toBe('75.00');
});

test('recalculate billable amounts command applies the same rate precedence', function () {
    SystemSetting::upsertValues(['default_hourly_rate' => '50.00']);

    $client = Client::factory()->withRate(80)->create();
    $inheritsClient = Project::factory()->create(['client_id' => $client->id, 'hourly_rate' => null]);
    $inheritsSystem = Project::factory()->create(['client_id' => null, 'hourly_rate' => null]);

    $clientEntry = TimeEntry::factory()->create(['project_id' => $inheritsClient->id, 'duration_minutes' => 30, 'is_billable' => true]);
    $systemEntry = TimeEntry::factory()->create(['project_id' => $inheritsSystem->id, 'duration_minutes' => 30, 'is_billable' => true]);

    // Change the client's rate behind the model's back, then recalculate.
    Client::query()->whereKey($client->id)->update(['default_hourly_rate' => 90]);

    $this->artisan('desk:recalculate-billable-amounts')->assertSuccessful();

    expect($clientEntry->fresh()->billable_amount)->toBe('45.00')
        ->and($systemEntry->fresh()->billable_amount)->toBe('25.00');
});

test('clients csv import shows invalid rows with reasons and writes nothing before commit', function () {
    Storage::fake('s3');
    $user = readyUserForClients();
    $existing = Client::factory()->create(['name' => 'Existing Co']);

    $csv = implode("\n", [
        'code,name,billing_email,currency,default_hourly_rate,status',
        ',New Co,new@co.example,SGD,110,active',
        $existing->client_code.',Existing Co Renamed,,SGD,,active',
        ',,missing-name@co.example,SGD,,active',
        ',Bad Email Co,not-an-email,SGD,,active',
        'CLT-99999,Unknown Code Co,,SGD,,active',
        ',Bad Status Co,,SGD,,deleted',
    ]);

    $response = $this->actingAs($user)->post(route('clients.import.preview'), [
        'csv_file' => UploadedFile::fake()->createWithContent('clients.csv', $csv),
    ]);

    $response->assertOk()
        ->assertSee('Line 4')
        ->assertSee('The name field is required.')
        ->assertSee('Line 5')
        ->assertSee('Line 6')
        ->assertSee('Unknown client code')
        ->assertSee('Line 7');

    expect(Client::query()->count())->toBe(1)
        ->and(Client::query()->where('name', 'New Co')->exists())->toBeFalse();

    $this->actingAs($user)->post(route('clients.import.commit'))->assertRedirect(route('clients.index'));

    expect(Client::query()->count())->toBe(2)
        ->and(Client::query()->where('name', 'New Co')->first()->client_code)->toBe('CLT-00002')
        ->and($existing->fresh()->name)->toBe('Existing Co Renamed');
});

test('clients import template can be downloaded', function () {
    $user = readyUserForClients();

    $response = $this->actingAs($user)->get(route('clients.import.template'));

    $response->assertOk();
    expect($response->streamedContent())->toStartWith('code,name,billing_email');
});

test('documents can be attached to a client', function () {
    Storage::fake('s3');
    $user = readyUserForClients();
    $client = Client::factory()->create();

    $this->actingAs($user)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf'),
        'documentable_type' => 'client',
        'documentable_uuid' => $client->uuid,
        'redirect_back' => 'client',
    ])->assertRedirect(route('clients.show', $client));

    expect($client->documents()->count())->toBe(1);

    $unlinked = Document::factory()->create(['documentable_type' => null, 'documentable_id' => null]);

    $this->actingAs($user)->post(route('documents.attach', $unlinked), [
        'documentable_type' => 'client',
        'documentable_uuid' => $client->uuid,
        'redirect_back' => 'client',
    ])->assertRedirect(route('clients.show', $client));

    expect($client->documents()->count())->toBe(2);
    $this->actingAs($user)->get(route('clients.show', $client))->assertSee('contract.pdf');
});

test('client create, update and archive are audit logged with old and new values', function () {
    $user = readyUserForClients();

    $this->actingAs($user)->post(route('clients.store'), clientPayload());
    $client = Client::query()->firstOrFail();

    $this->actingAs($user)->put(route('clients.update', $client), clientPayload(['default_hourly_rate' => '150.00']));
    $this->actingAs($user)->put(route('clients.update', $client), clientPayload(['default_hourly_rate' => '150.00', 'status' => 'archived']));

    $logs = AuditLog::query()->where('auditable_type', Client::class)->where('auditable_id', $client->id)->orderBy('id')->get();

    expect($logs->pluck('action')->all())->toBe(['created', 'updated', 'updated'])
        ->and($logs[0]->user_id)->toBe($user->id)
        ->and($logs[1]->old_values['default_hourly_rate'])->toBe('120.00')
        ->and((float) $logs[1]->new_values['default_hourly_rate'])->toBe(150.0)
        ->and($logs[2]->old_values['status'])->toBe('active')
        ->and($logs[2]->new_values['status'])->toBe('archived');
});

test('archived clients are hidden from the project client picker but kept on linked projects', function () {
    $user = readyUserForClients();
    $archived = Client::factory()->archived()->create(['name' => 'Old Client']);
    $project = Project::factory()->create(['client_id' => $archived->id]);

    $this->actingAs($user)->get(route('projects.create'))->assertOk()->assertDontSee('Old Client');
    $this->actingAs($user)->get(route('projects.edit', $project))->assertOk()->assertSee('Old Client');
});

test('in archive mode every client write is blocked', function () {
    Storage::fake('s3');
    $user = readyUserForClients();
    $client = Client::factory()->create(['name' => 'Before']);
    SystemSetting::upsertValues(['archive_mode' => true]);

    $this->actingAs($user)->post(route('clients.store'), clientPayload(['name' => 'Blocked']))
        ->assertSessionHas('status', 'archive-mode-read-only');
    $this->actingAs($user)->put(route('clients.update', $client), clientPayload(['name' => 'After']))
        ->assertSessionHas('status', 'archive-mode-read-only');
    $this->actingAs($user)->post(route('clients.import.preview'), [
        'csv_file' => UploadedFile::fake()->createWithContent('clients.csv', "name\nBlocked Import"),
    ])->assertSessionHas('status', 'archive-mode-read-only');
    $this->actingAs($user)->post(route('clients.import.commit'))
        ->assertSessionHas('status', 'archive-mode-read-only');

    expect(Client::query()->count())->toBe(1)
        ->and($client->fresh()->name)->toBe('Before');

    $this->actingAs($user)->get(route('clients.show', $client))->assertOk();
});

test('client routes require authentication', function () {
    $client = Client::factory()->create();

    $this->get(route('clients.index'))->assertRedirect(route('login'));
    $this->get(route('clients.show', $client))->assertRedirect(route('login'));
    $this->post(route('clients.store'), clientPayload())->assertRedirect(route('login'));
});
