<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

function makeProjectWithRate(string $code, float $hourlyRate): Project
{
    return Project::query()->create([
        'code' => $code,
        'name' => "Project {$code}",
        'status' => 'active',
        'is_billable' => true,
        'hourly_rate' => $hourlyRate,
    ]);
}

function makeEntry(Project $project, int $durationMinutes, bool $isBillable): TimeEntry
{
    $stage = ProjectStage::query()->first() ?? ProjectStage::query()->create([
        'name' => 'Development',
        'sort_order' => 4,
        'status' => 'active',
    ]);

    return TimeEntry::query()->create([
        'project_id' => $project->id,
        'project_stage_id' => $stage->id,
        'entry_date' => now()->toDateString(),
        'duration_minutes' => $durationMinutes,
        'is_billable' => $isBillable,
    ]);
}

test('recalculate billable amounts command uses each project hourly rate', function () {
    $p1 = makeProjectWithRate('BIL-001', 100);
    $p2 = makeProjectWithRate('BIL-002', 150);

    $e1 = makeEntry($p1, 120, true);  // 120/60 * 100 = 200
    $e2 = makeEntry($p2, 60, true);   // 60/60  * 150 = 150
    $e3 = makeEntry($p1, 90, false);  // not billable → 0

    // Corrupt the stored amounts to confirm the command resets them
    DB::table('time_entries')->update(['billable_amount' => 9999]);

    $this->artisan('desk:recalculate-billable-amounts')
        ->expectsOutputToContain('Recalculating billable amounts for 3')
        ->expectsOutputToContain('recalculated successfully')
        ->assertExitCode(0);

    expect((float) $e1->fresh()->billable_amount)->toBe(200.0)
        ->and((float) $e2->fresh()->billable_amount)->toBe(150.0)
        ->and((float) $e3->fresh()->billable_amount)->toBe(0.0);
});

test('recalculate billable amounts command zeros non-billable entries', function () {
    $project = makeProjectWithRate('BIL-003', 200);
    $entry = makeEntry($project, 60, false);

    DB::table('time_entries')->update(['billable_amount' => 500]);

    $this->artisan('desk:recalculate-billable-amounts')->assertExitCode(0);

    expect((float) $entry->fresh()->billable_amount)->toBe(0.0);
});

test('recalculate billable amounts command handles empty table', function () {
    $this->artisan('desk:recalculate-billable-amounts')
        ->expectsOutputToContain('No time entries found')
        ->assertExitCode(0);
});
