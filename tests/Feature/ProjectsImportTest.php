<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

test('projects csv can be previewed and committed by code mapping while ignoring id columns', function () {
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

    $commitResponse = $this->actingAs($user)
        ->post(route('projects.import.commit'));

    $commitResponse->assertRedirect(route('projects.index'));

    $updatedProject = Project::query()->where('code', 'PRJ-IMP-001')->firstOrFail();
    $newProject = Project::query()->where('code', 'PRJ-IMP-002')->firstOrFail();

    expect($updatedProject->name)->toBe('Updated Project Name')
        ->and($updatedProject->description)->toBe('Updated by import')
        ->and($updatedProject->is_billable)->toBeTrue()
        ->and($newProject->name)->toBe('Imported New Project')
        ->and($newProject->is_billable)->toBeFalse()
        ->and($newProject->uuid)->not()->toBe('22222222-2222-2222-2222-222222222222');
});

test('projects import routes require authentication', function () {
    $this->get(route('projects.import.create'))->assertRedirect(route('login'));
});
