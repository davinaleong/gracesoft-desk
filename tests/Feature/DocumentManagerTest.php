<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function readyUserForDocuments(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('documents index is accessible', function () {
    $user = readyUserForDocuments();

    $this->actingAs($user)
        ->get(route('documents.index'))
        ->assertOk()
        ->assertSee(__('Documents'));
});

test('guests are redirected from documents index', function () {
    $this->get(route('documents.index'))
        ->assertRedirect(route('login'));
});

test('document can be uploaded to s3', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'file' => $file,
            'name' => 'Test Invoice',
        ])
        ->assertRedirect(route('documents.index'))
        ->assertSessionHas('status', 'document-uploaded');

    $document = Document::query()->where('name', 'Test Invoice')->first();
    expect($document)->not->toBeNull();
    Storage::disk('s3')->assertExists($document->path);
});

test('document upload validates file type', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $file = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

    $this->actingAs($user)
        ->post(route('documents.store'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

test('document upload validates file size', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $file = UploadedFile::fake()->create('big.pdf', 60_000, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

test('document can be linked to a transaction on upload', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $transaction = Transaction::factory()->create(['amount' => 500, 'gst_amount' => 50, 'net_amount' => 450]);
    $file = UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'file' => $file,
            'documentable_type' => 'transaction',
            'documentable_uuid' => $transaction->uuid,
            'redirect_back' => 'transaction',
        ])
        ->assertRedirect(route('transactions.show', $transaction))
        ->assertSessionHas('status', 'document-uploaded');

    expect($transaction->documents()->count())->toBe(1);
});

test('document can be deleted', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $file = UploadedFile::fake()->create('to-delete.pdf', 50, 'application/pdf');

    $this->actingAs($user)->post(route('documents.store'), ['file' => $file]);

    $document = Document::query()->first();
    $path = $document->path;

    $this->actingAs($user)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents.index'))
        ->assertSessionHas('status', 'document-deleted');

    expect(Document::query()->find($document->id))->toBeNull();
    Storage::disk('s3')->assertMissing($path);
});

test('document download redirects to signed url', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['user_id' => $user->id]);
    Storage::disk('s3')->put($document->path, 'dummy content');

    $this->actingAs($user)
        ->get(route('documents.download', $document))
        ->assertRedirect();
});

test('document preview redirects to signed url', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['user_id' => $user->id]);
    Storage::disk('s3')->put($document->path, 'dummy content');

    $this->actingAs($user)
        ->get(route('documents.preview', $document))
        ->assertRedirect();
});

test('transaction show page displays linked documents', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $transaction = Transaction::factory()->create();
    $document = Document::factory()->create([
        'documentable_type' => Transaction::class,
        'documentable_id' => $transaction->id,
        'name' => 'linked-receipt.pdf',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('linked-receipt.pdf');
});

test('document can be linked to a project on upload', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $project = Project::factory()->create();
    $file = UploadedFile::fake()->create('spec.pdf', 50, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'file' => $file,
            'documentable_type' => 'project',
            'documentable_uuid' => $project->uuid,
            'redirect_back' => 'project',
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('status', 'document-uploaded');

    expect($project->documents()->count())->toBe(1);
});

test('document can be linked to a time entry on upload', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $timeEntry = TimeEntry::factory()->create();
    $file = UploadedFile::fake()->create('timesheet.pdf', 50, 'application/pdf');

    $this->actingAs($user)
        ->post(route('documents.store'), [
            'file' => $file,
            'documentable_type' => 'time-entry',
            'documentable_uuid' => $timeEntry->uuid,
            'redirect_back' => 'time-entry',
        ])
        ->assertRedirect(route('time-entries.show', $timeEntry))
        ->assertSessionHas('status', 'document-uploaded');

    expect($timeEntry->documents()->count())->toBe(1);
});

test('project show page displays linked documents', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $project = Project::factory()->create();
    $document = Document::factory()->create([
        'documentable_type' => Project::class,
        'documentable_id' => $project->id,
        'name' => 'project-brief.pdf',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('project-brief.pdf');
});

test('time entry show page displays linked documents', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $timeEntry = TimeEntry::factory()->create();
    $document = Document::factory()->create([
        'documentable_type' => TimeEntry::class,
        'documentable_id' => $timeEntry->id,
        'name' => 'timesheet-export.pdf',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('time-entries.show', $timeEntry))
        ->assertOk()
        ->assertSee('timesheet-export.pdf');
});

test('document name can be updated', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['name' => 'old-name.pdf', 'user_id' => $user->id]);
    Storage::disk('s3')->put($document->path, 'dummy');

    $this->actingAs($user)
        ->put(route('documents.update', $document), ['name' => 'new-name.pdf'])
        ->assertRedirect(route('documents.index'))
        ->assertSessionHas('status', 'document-updated');

    expect($document->fresh()->name)->toBe('new-name.pdf');
});

test('document edit page is accessible', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['name' => 'test.pdf', 'user_id' => $user->id]);
    Storage::disk('s3')->put($document->path, 'dummy');

    $this->actingAs($user)
        ->get(route('documents.edit', $document))
        ->assertOk()
        ->assertSee('test.pdf');
});

test('document name update requires a name', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['name' => 'test.pdf', 'user_id' => $user->id]);
    Storage::disk('s3')->put($document->path, 'dummy');

    $this->actingAs($user)
        ->put(route('documents.update', $document), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('existing document can be attached to a transaction', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $transaction = Transaction::factory()->create(['amount' => 200, 'gst_amount' => 20, 'net_amount' => 180]);
    $document = Document::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('documents.attach', $document), [
            'documentable_type' => 'transaction',
            'documentable_uuid' => $transaction->uuid,
            'redirect_back' => 'transaction',
        ])
        ->assertRedirect(route('transactions.show', $transaction))
        ->assertSessionHas('status', 'document-attached');

    expect($document->fresh()->documentable_id)->toBe($transaction->id);
    expect($document->fresh()->documentable_type)->toBe(Transaction::class);
});

test('existing document can be attached to a project', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $project = Project::factory()->create();
    $document = Document::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('documents.attach', $document), [
            'documentable_type' => 'project',
            'documentable_uuid' => $project->uuid,
            'redirect_back' => 'project',
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('status', 'document-attached');

    expect($document->fresh()->documentable_id)->toBe($project->id);
    expect($document->fresh()->documentable_type)->toBe(Project::class);
});

test('existing document can be attached to a time entry', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $timeEntry = TimeEntry::factory()->create();
    $document = Document::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('documents.attach', $document), [
            'documentable_type' => 'time-entry',
            'documentable_uuid' => $timeEntry->uuid,
            'redirect_back' => 'time-entry',
        ])
        ->assertRedirect(route('time-entries.show', $timeEntry))
        ->assertSessionHas('status', 'document-attached');

    expect($document->fresh()->documentable_id)->toBe($timeEntry->id);
    expect($document->fresh()->documentable_type)->toBe(TimeEntry::class);
});

test('attach requires valid documentable type', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $document = Document::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('documents.attach', $document), [
            'documentable_type' => 'invalid',
            'documentable_uuid' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('documentable_type');
});

test('transaction show page displays link existing button when unlinked documents exist', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $transaction = Transaction::factory()->create(['amount' => 100, 'gst_amount' => 10, 'net_amount' => 90]);
    Document::factory()->create(['user_id' => $user->id, 'name' => 'unlinked-file.pdf']);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee(__('Link Existing'));
});

test('project show page displays link existing button when unlinked documents exist', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $project = Project::factory()->create();
    Document::factory()->create(['user_id' => $user->id, 'name' => 'unlinked-file.pdf']);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee(__('Link Existing'));
});

test('time entry show page displays link existing button when unlinked documents exist', function () {
    Storage::fake('s3');

    $user = readyUserForDocuments();
    $timeEntry = TimeEntry::factory()->create();
    Document::factory()->create(['user_id' => $user->id, 'name' => 'unlinked-file.pdf']);

    $this->actingAs($user)
        ->get(route('time-entries.show', $timeEntry))
        ->assertOk()
        ->assertSee(__('Link Existing'));
});
