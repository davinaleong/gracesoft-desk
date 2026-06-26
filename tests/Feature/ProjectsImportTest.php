<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function readyUserForProjectsImport(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('projects import page is accessible', function () {
    $user = readyUserForProjectsImport();

    $this->actingAs($user)
        ->get(route('projects.import.create'))
        ->assertOk()
        ->assertSee('Import Projects CSV');
});

test('projects import template csv can be downloaded', function () {
    $user = readyUserForProjectsImport();

    $this->actingAs($user)
        ->get(route('projects.import.template'))
        ->assertOk()
        ->assertDownload('projects-import-template.csv')
        ->assertStreamedContent("code,name,status,description,starts_on,ends_on,is_billable\nPRJ-001,\"Website Revamp\",active,\"Q3 delivery scope\",2026-07-01,2026-09-30,yes\n");
});

test('projects csv can be previewed and committed by code mapping while ignoring id columns', function () {
    Storage::fake('s3');

    $user = readyUserForProjectsImport();

    Project::query()->create([
        'code' => 'PRJ-IMP-001',
        'name' => 'Old Name',
        'status' => 'active',
        'is_billable' => false,
    ]);

    $csvContent = implode("\n", [
        'id,uuid,code,name,status,description,starts_on,ends_on,is_billable',
        '77,11111111-1111-1111-1111-111111111111,prj-imp-001,Updated Project Name,active,Updated by import,2026-05-01,2026-06-01,yes',
        '88,22222222-2222-2222-2222-222222222222,PRJ-IMP-002,Imported New Project,paused,New row,2026-05-02,2026-06-02,no',
    ]);

    $file = UploadedFile::fake()->createWithContent('projects.csv', $csvContent);

    $previewResponse = $this->actingAs($user)
        ->post(route('projects.import.preview'), [
            'csv_file' => $file,
        ]);

    $previewResponse->assertOk()->assertSee('Projects Import Preview');

    expect(session('projects_import_rows'))->toBeArray()->toHaveCount(2);

    $csvPath = session('projects_import_csv_path');
    expect($csvPath)->toBeString()->toStartWith('imports/projects/');
    Storage::disk('s3')->assertExists($csvPath);

    $commitResponse = $this->actingAs($user)
        ->post(route('projects.import.commit'));

    $commitResponse->assertRedirect(route('projects.index'));

    Storage::disk('s3')->assertMissing($csvPath);

    $updatedProject = Project::query()->where('code', 'PRJ-IMP-001')->firstOrFail();
    $newProject = Project::query()->where('code', 'PRJ-IMP-002')->firstOrFail();

    expect($updatedProject->name)->toBe('Updated Project Name')
        ->and($updatedProject->description)->toBe('Updated by import')
        ->and($updatedProject->is_billable)->toBeTrue()
        ->and($newProject->name)->toBe('Imported New Project')
        ->and($newProject->is_billable)->toBeFalse()
        ->and($newProject->uuid)->not()->toBe('22222222-2222-2222-2222-222222222222');
});

test('re-uploading projects csv for preview removes the previous file from s3', function () {
    Storage::fake('s3');

    $user = readyUserForProjectsImport();

    $csvContent = implode("\n", [
        'code,name,status,is_billable',
        'PRJ-REUP-001,Re-upload Project,active,yes',
    ]);

    $firstFile = UploadedFile::fake()->createWithContent('first.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('projects.import.preview'), ['csv_file' => $firstFile])
        ->assertOk();

    $firstPath = session('projects_import_csv_path');
    Storage::disk('s3')->assertExists($firstPath);

    $secondFile = UploadedFile::fake()->createWithContent('second.csv', $csvContent);

    $this->actingAs($user)
        ->post(route('projects.import.preview'), ['csv_file' => $secondFile])
        ->assertOk();

    $secondPath = session('projects_import_csv_path');

    Storage::disk('s3')->assertMissing($firstPath);
    Storage::disk('s3')->assertExists($secondPath);
    expect($secondPath)->not()->toBe($firstPath);
});

test('projects import routes require authentication', function () {
    $this->get(route('projects.import.create'))->assertRedirect(route('login'));
    $this->get(route('projects.import.template'))->assertRedirect(route('login'));
});
