<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class CsvProjectWorklogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataDirectory = base_path('_internal-docs/data');

        if (! is_dir($dataDirectory)) {
            return;
        }

        $csvFiles = glob($dataDirectory.'/GS-*.csv');

        if (! is_array($csvFiles) || $csvFiles === []) {
            return;
        }

        $defaultUserId = User::query()->value('id');

        foreach ($csvFiles as $csvFilePath) {
            if (! is_string($csvFilePath)) {
                continue;
            }

            $rows = $this->readCsvRows($csvFilePath);

            if ($rows === []) {
                continue;
            }

            $projectCode = strtoupper(trim((string) ($rows[0]['project id'] ?? '')));

            if ($projectCode === '') {
                continue;
            }

            $project = Project::query()->firstOrCreate(
                ['code' => $projectCode],
                [
                    'name' => $projectCode,
                    'status' => 'active',
                    'is_billable' => true,
                ]
            );

            $this->syncProjectDates($project, $rows);

            TimeEntry::query()->where('project_id', $project->id)->forceDelete();

            foreach ($rows as $row) {
                $stageName = trim((string) ($row['sdlc stage'] ?? ''));

                if ($stageName === '') {
                    continue;
                }

                $stage = $this->resolveStage($stageName);

                $durationHours = $this->parseDecimal($row['duration (hours)'] ?? null);
                $durationMinutes = max(1, (int) round($durationHours * 60));
                $billableCost = $this->parseDecimal($row['billable cost (sgd)'] ?? null);
                $entryDate = trim((string) ($row['started date'] ?? ''));

                if ($entryDate === '') {
                    continue;
                }

                $notesParts = array_filter([
                    trim((string) ($row['description'] ?? '')),
                    trim((string) ($row['remarks'] ?? '')),
                    $this->buildTimeRangeNote($row),
                ]);

                $notes = implode(' | ', $notesParts);

                TimeEntry::query()->create([
                    'project_id' => $project->id,
                    'project_stage_id' => $stage->id,
                    'entry_date' => $entryDate,
                    'duration_minutes' => $durationMinutes,
                    'notes' => $notes,
                    'user_id' => $defaultUserId,
                    'is_billable' => $billableCost > 0,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readCsvRows(string $csvFilePath): array
    {
        $handle = fopen($csvFilePath, 'rb');

        if (! is_resource($handle)) {
            return [];
        }

        // Skip header row.
        fgetcsv($handle, 0, ',', '"', '\\');

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $mapped = $this->mapWorklogRow($row);

            if ($mapped === []) {
                continue;
            }

            $rows[] = $mapped;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function mapWorklogRow(array $row): array
    {
        if (count($row) < 10) {
            return [];
        }

        $descriptionPartsCount = count($row) - 9;
        $description = implode(',', array_slice($row, 2, $descriptionPartsCount));

        return [
            'project id' => $row[0] ?? null,
            'sdlc stage' => $row[1] ?? null,
            'description' => $description,
            'started date' => $row[2 + $descriptionPartsCount] ?? null,
            'started time' => $row[3 + $descriptionPartsCount] ?? null,
            'ended date' => $row[4 + $descriptionPartsCount] ?? null,
            'ended time' => $row[5 + $descriptionPartsCount] ?? null,
            'duration (hours)' => $row[6 + $descriptionPartsCount] ?? null,
            'billable cost (sgd)' => $row[7 + $descriptionPartsCount] ?? null,
            'remarks' => $row[8 + $descriptionPartsCount] ?? null,
        ];
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function syncProjectDates(Project $project, array $rows): void
    {
        $startedDates = [];
        $endedDates = [];

        foreach ($rows as $row) {
            $started = trim((string) ($row['started date'] ?? ''));
            $ended = trim((string) ($row['ended date'] ?? ''));

            if ($started !== '') {
                $startedDates[] = $started;
            }

            if ($ended !== '') {
                $endedDates[] = $ended;
            }
        }

        sort($startedDates);
        sort($endedDates);

        $project->update([
            'starts_on' => $startedDates[0] ?? $project->starts_on,
            'ends_on' => $endedDates !== [] ? $endedDates[count($endedDates) - 1] : $project->ends_on,
        ]);
    }

    private function resolveStage(string $stageName): ProjectStage
    {
        $normalized = strtolower(trim($stageName));

        $stageNameByAlias = [
            'discovery' => 'Discovery',
            'planning' => 'Analysis',
            'analysis' => 'Analysis',
            'design' => 'Design',
            'development' => 'Development',
            'execution' => 'Development',
            'implementation' => 'Development',
            'testing' => 'Testing',
            'deployment' => 'Deployment',
            'maintenance' => 'Maintenance',
        ];

        $resolvedName = $stageNameByAlias[$normalized] ?? 'Analysis';

        $sortOrderByName = [
            'Discovery' => 1,
            'Analysis' => 2,
            'Design' => 3,
            'Development' => 4,
            'Testing' => 5,
            'Deployment' => 6,
            'Maintenance' => 7,
        ];

        return ProjectStage::query()->updateOrCreate(
            ['name' => $resolvedName],
            [
                'sort_order' => $sortOrderByName[$resolvedName] ?? 99,
                'status' => 'active',
            ]
        );
    }

    private function parseDecimal(mixed $value): float
    {
        $raw = str_replace([',', '$'], '', trim((string) $value));

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function buildTimeRangeNote(array $row): string
    {
        $startedDate = trim((string) ($row['started date'] ?? ''));
        $startedTime = trim((string) ($row['started time'] ?? ''));
        $endedDate = trim((string) ($row['ended date'] ?? ''));
        $endedTime = trim((string) ($row['ended time'] ?? ''));

        if ($startedDate === '' && $endedDate === '') {
            return '';
        }

        return sprintf(
            'Window: %s %s -> %s %s',
            $startedDate,
            $startedTime,
            $endedDate,
            $endedTime
        );
    }
}
