<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTimeEntriesCsvRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, ['project_uuid', 'project_code', 'stage_uuid', 'stage_name', 'entry_date', 'duration_minutes', 'is_billable', 'hourly_rate', 'notes']);
            fputcsv($handle, ['', 'PRJ-001', '', 'Execution', '2026-07-15', '90', 'yes', '120', 'Frontend build and QA']);

            fclose($handle);
        }, 'time-entries-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('time-entries.import');
    }

    public function preview(ImportTimeEntriesCsvRequest $request): View
    {
        $parsed = $this->parseCsv($request->file('csv_file'));
        $references = $this->buildReferences($parsed['valid_rows']);

        return view('time-entries.import-preview', [
            'headers' => $parsed['headers'],
            'validRows' => $parsed['valid_rows'],
            'invalidRows' => $parsed['invalid_rows'],
            'missingHeaders' => $parsed['missing_headers'],
            'projectReferences' => $references['projects'],
            'stageReferences' => $references['stages'],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $rows = $request->session()->get('time_entries_import_rows', []);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('time-entries.import.create')
                ->with('status', 'No import data found. Upload and preview a CSV file first.');
        }

        $createdCount = 0;

        foreach ($rows as $row) {
            TimeEntry::query()->create($row);
            $createdCount++;
        }

        $request->session()->forget('time_entries_import_rows');

        return redirect()
            ->route('time-entries.index')
            ->with('status', sprintf('Time entries import completed. Created: %d.', $createdCount));
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     valid_rows: array<int, array<string, mixed>>,
     *     invalid_rows: array<int, array<string, mixed>>,
     *     missing_headers: array<int, string>
     * }
     */
    private function parseCsv(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $path = is_string($realPath) && $realPath !== '' ? $realPath : $file->getPathname();

        if (! is_string($path) || $path === '') {
            return [
                'headers' => [],
                'valid_rows' => [],
                'invalid_rows' => [[
                    'line' => 0,
                    'errors' => ['Unable to read uploaded CSV file.'],
                    'raw' => [],
                ]],
                'missing_headers' => [],
            ];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [
                'headers' => [],
                'valid_rows' => [],
                'invalid_rows' => [[
                    'line' => 0,
                    'errors' => ['Unable to read uploaded CSV file.'],
                    'raw' => [],
                ]],
                'missing_headers' => [],
            ];
        }

        $headerRow = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), $headerRow);

        $requiredHeaders = ['entry_date', 'duration_minutes'];
        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));

        $validRows = [];
        $invalidRows = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($row === [null] || $row === []) {
                continue;
            }

            $paddedRow = array_pad($row, count($headers), null);
            /** @var array<string, mixed> $mapped */
            $mapped = array_combine($headers, $paddedRow) ?: [];

            $project = $this->resolveProject($mapped);

            if (! $project) {
                $invalidRows[] = [
                    'line' => $line,
                    'errors' => ['Unable to resolve project by project_uuid or project_code.'],
                    'raw' => $mapped,
                ];

                continue;
            }

            $stage = $this->resolveStage($mapped, $project->id);

            if (($mapped['stage_uuid'] ?? null) || ($mapped['stage_name'] ?? null)) {
                if (($mapped['stage_uuid'] ?? null || $mapped['stage_name'] ?? null) && ! $stage) {
                    $invalidRows[] = [
                        'line' => $line,
                        'errors' => ['Unable to resolve stage by stage_uuid or stage_name for the resolved project.'],
                        'raw' => $mapped,
                    ];

                    continue;
                }
            }

            $durationMinutes = (int) ($mapped['duration_minutes'] ?? 0);
            $isBillable = $this->normalizeBoolean($mapped['is_billable'] ?? '1');
            $hourlyRate = $this->nullableFloat($mapped['hourly_rate'] ?? null);

            $payload = [
                'project_id' => $project->id,
                'project_stage_id' => $stage?->id,
                'user_id' => request()->user()?->id,
                'entry_date' => trim((string) ($mapped['entry_date'] ?? '')),
                'duration_minutes' => $durationMinutes,
                'is_billable' => $isBillable,
                'hourly_rate' => $hourlyRate,
                'billable_amount' => $this->calculateBillableAmount($durationMinutes, $isBillable, $hourlyRate),
                'notes' => $this->nullableString($mapped['notes'] ?? null),
            ];

            $validator = Validator::make($payload, [
                'project_id' => ['required', 'integer', 'exists:projects,id'],
                'project_stage_id' => ['nullable', 'integer', 'exists:project_stages,id'],
                'user_id' => ['nullable', 'integer', 'exists:users,id'],
                'entry_date' => ['required', 'date'],
                'duration_minutes' => ['required', 'integer', 'min:1'],
                'is_billable' => ['required', 'boolean'],
                'hourly_rate' => ['nullable', 'numeric', 'min:0'],
                'billable_amount' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $invalidRows[] = [
                    'line' => $line,
                    'errors' => $validator->errors()->all(),
                    'raw' => $mapped,
                ];

                continue;
            }

            $validRows[] = $validator->validated();
        }

        fclose($handle);

        request()->session()->put('time_entries_import_rows', $validRows);

        return [
            'headers' => $headers,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'missing_headers' => $missingHeaders,
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveProject(array $mapped): ?Project
    {
        $projectUuid = $this->nullableString($mapped['project_uuid'] ?? null);
        $projectCode = strtoupper((string) $this->nullableString($mapped['project_code'] ?? null));

        if ($projectUuid) {
            $project = Project::query()->where('uuid', $projectUuid)->first();

            if ($project) {
                return $project;
            }
        }

        if ($projectCode) {
            return Project::query()->where('code', $projectCode)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveStage(array $mapped, int $projectId): ?ProjectStage
    {
        $stageUuid = $this->nullableString($mapped['stage_uuid'] ?? null);
        $stageName = $this->nullableString($mapped['stage_name'] ?? null);

        if ($stageUuid) {
            $stage = ProjectStage::query()
                ->where('project_id', $projectId)
                ->where('uuid', $stageUuid)
                ->first();

            if ($stage) {
                return $stage;
            }
        }

        if ($stageName) {
            return ProjectStage::query()
                ->where('project_id', $projectId)
                ->whereRaw('LOWER(name) = ?', [strtolower($stageName)])
                ->first();
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function nullableFloat(mixed $value): ?float
    {
        $normalized = $this->nullableString($value);

        return is_null($normalized) ? null : (float) $normalized;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    private function calculateBillableAmount(int $durationMinutes, bool $isBillable, ?float $hourlyRate): float
    {
        if (! $isBillable || $durationMinutes <= 0 || is_null($hourlyRate) || $hourlyRate <= 0) {
            return 0;
        }

        return round(($durationMinutes / 60) * $hourlyRate, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $validRows
     * @return array{projects: array<int, string>, stages: array<int, string>}
     */
    private function buildReferences(array $validRows): array
    {
        $projectIds = collect($validRows)
            ->pluck('project_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $stageIds = collect($validRows)
            ->pluck('project_stage_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $projectReferences = Project::query()
            ->whereIn('id', $projectIds)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Project $project): array => [
                $project->id => sprintf('%s - %s', $project->code, $project->name),
            ])
            ->all();

        $stageReferences = ProjectStage::query()
            ->whereIn('id', $stageIds)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ProjectStage $stage): array => [
                $stage->id => $stage->name,
            ])
            ->all();

        return [
            'projects' => $projectReferences,
            'stages' => $stageReferences,
        ];
    }
}
