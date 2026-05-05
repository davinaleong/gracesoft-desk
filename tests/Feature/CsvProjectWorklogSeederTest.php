<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Database\Seeders\CsvProjectWorklogSeeder;
use Database\Seeders\ProjectMapSeeder;
use Database\Seeders\ProjectStageSeeder;

test('csv project worklog seeders map project data into database schemas', function () {
    $this->seed([
        ProjectStageSeeder::class,
        ProjectMapSeeder::class,
        CsvProjectWorklogSeeder::class,
    ]);

    expect(Project::query()->where('code', 'GS-HQ')->exists())->toBeTrue()
        ->and(Project::query()->where('code', 'GS-WEB')->exists())->toBeTrue()
        ->and(Project::query()->where('code', 'GS-BEAC')->exists())->toBeTrue();

    expect(ProjectStage::query()->where('slug', 'analysis')->exists())->toBeTrue()
        ->and(ProjectStage::query()->where('slug', 'design')->exists())->toBeTrue();

    $importedEntriesCount = TimeEntry::query()->count();

    expect($importedEntriesCount)->toBeGreaterThan(40)
        ->and(TimeEntry::query()
            ->whereHas('project', fn ($query) => $query->where('code', 'GS-BEAC'))
            ->whereHas('stage', fn ($query) => $query->where('slug', 'planning'))
            ->exists())->toBeTrue();

    $this->seed(CsvProjectWorklogSeeder::class);

    expect(TimeEntry::query()->count())->toBe($importedEntriesCount);
});
