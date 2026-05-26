<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function readyUserForTimeEntriesImport(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('time entries import page is accessible', function () {
    $user = readyUserForTimeEntriesImport();

    $this->actingAs($user)
        ->get(route('time-entries.import.create'))
        ->assertOk()
        ->assertSee('Import Time Entries CSV');
});

test('time entries import template csv can be downloaded', function () {
    $user = readyUserForTimeEntriesImport();

    $this->actingAs($user)
        ->get(route('time-entries.import.template'))
        ->assertOk()
        ->assertDownload('time-entries-import-template.csv')
        ->assertStreamedContent("project_uuid,project_code,stage_uuid,stage_name,entry_date,duration_minutes,is_billable,hourly_rate,notes\n,PRJ-001,,Execution,2026-07-15,90,yes,120,\"Frontend build and QA\"\n");
});

test('time entries csv can be previewed and committed with uuid-aware mapping', function () {
    $user = readyUserForTimeEntriesImport();

    $project = Project::query()->create([
        'code' => 'PRJ-TIM-001',
        'name' => 'Time Import Project',
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

    $csvContent = implode("\n", [
        'id,uuid,project_uuid,project_code,stage_uuid,stage_name,entry_date,duration_minutes,is_billable,hourly_rate,notes',
        '99,aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa,'.$project->uuid.',UNKNOWN-CODE,'.$stage->uuid.',Wrong Stage Name,2026-05-05,90,yes,120,Imported from UUID mapping',
        '100,bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb,,PRJ-TIM-001,,Execution,2026-05-06,30,no,150,Imported by code + stage name',
    ]);

    $file = UploadedFile::fake()->createWithContent('time-entries.csv', $csvContent);

    $previewResponse = $this->actingAs($user)
        ->post(route('time-entries.import.preview'), [
            'csv_file' => $file,
        ]);

    $previewResponse->assertOk()
        ->assertSee('Time Entries Import Preview')
        ->assertSee($project->code.' - '.$project->name)
        ->assertSee($stage->name);

    expect(session('time_entries_import_rows'))->toBeArray()->toHaveCount(2);

    $commitResponse = $this->actingAs($user)
        ->post(route('time-entries.import.commit'));

    $commitResponse->assertRedirect(route('time-entries.index'));

    expect(TimeEntry::query()->count())->toBe(2)
        ->and(TimeEntry::query()->where('notes', 'Imported from UUID mapping')->exists())->toBeTrue()
        ->and(TimeEntry::query()->where('notes', 'Imported by code + stage name')->exists())->toBeTrue();

    $uuidMappedRow = TimeEntry::query()->where('notes', 'Imported from UUID mapping')->firstOrFail();

    expect($uuidMappedRow->project_id)->toBe($project->id)
        ->and($uuidMappedRow->project_stage_id)->toBe($stage->id)
        ->and((float) $uuidMappedRow->billable_amount)->toBe(180.0)
        ->and($uuidMappedRow->uuid)->not()->toBe('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
});

test('time entries csv resolves stage name from global stages when project has no project-specific stage', function () {
    $user = readyUserForTimeEntriesImport();

    $project = Project::query()->create([
        'code' => 'PRJ-TIM-002',
        'name' => 'Global Stage Import Project',
        'status' => 'active',
        'is_billable' => true,
    ]);

    $globalStage = ProjectStage::query()->create([
        'project_id' => null,
        'name' => 'Planning',
        'slug' => 'planning-global',
        'sort_order' => 2,
        'status' => 'active',
    ]);

    $csvContent = implode("\n", [
        'project_uuid,project_code,stage_uuid,stage_name,entry_date,duration_minutes,is_billable,hourly_rate,notes',
        ',PRJ-TIM-002,,Planning,2026-05-07,60,yes,120,Imported using global stage fallback',
    ]);

    $file = UploadedFile::fake()->createWithContent('time-entries-global-stage.csv', $csvContent);

    $previewResponse = $this->actingAs($user)
        ->post(route('time-entries.import.preview'), [
            'csv_file' => $file,
        ]);

    $previewResponse->assertOk()
        ->assertSee('Time Entries Import Preview')
        ->assertSee($project->code.' - '.$project->name)
        ->assertSee($globalStage->name)
        ->assertSee('Valid rows:');

    expect(session('time_entries_import_rows'))->toBeArray()->toHaveCount(1);

    $commitResponse = $this->actingAs($user)
        ->post(route('time-entries.import.commit'));

    $commitResponse->assertRedirect(route('time-entries.index'));

    $importedRow = TimeEntry::query()->where('notes', 'Imported using global stage fallback')->firstOrFail();

    expect($importedRow->project_id)->toBe($project->id)
        ->and($importedRow->project_stage_id)->toBe($globalStage->id);
});

test('time entries import routes require authentication', function () {
    $this->get(route('time-entries.import.create'))->assertRedirect(route('login'));
    $this->get(route('time-entries.import.template'))->assertRedirect(route('login'));
});
