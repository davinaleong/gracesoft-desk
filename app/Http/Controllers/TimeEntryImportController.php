<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTimeEntriesCsvRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            fputcsv($handle, ['', 'PRJ-001', '', 'Development', '2026-07-15', '90', 'yes', '120', 'Frontend build and QA']);

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
        $previousPath = $request->session()->get('time_entries_import_csv_path');
        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk('s3')->delete($previousPath);
        }

        $file = $request->file('csv_file');
        $storagePath = 'imports/time-entries/'.Str::uuid().'.csv';
        Storage::disk('s3')->put($storagePath, file_get_contents($file->getRealPath()));
        $request->session()->put('time_entries_import_csv_path', $storagePath);

        $parsed = $this->parseCsv($file);
        $references = $this->buildReferences($parsed['valid_rows']);

        return view('time-entries.import-preview', [
            'csvPath' => $storagePath,
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
        $validRows = $this->resolveImportRows($request);

        if ($validRows === null) {
            return redirect()
                ->route('time-entries.import.create')
                ->with('status', 'No import data found. Please upload and preview a CSV file first.');
        }

        $csvPath = (string) $request->input('csv_path', '');

        if ($validRows === []) {
            $this->cleanupCsvFromS3($csvPath);

            return redirect()
                ->route('time-entries.import.create')
                ->with('status', 'No valid rows found in the import file.');
        }

        $createdCount = 0;

        foreach (array_chunk($validRows, 50) as $chunk) {
            DB::transaction(function () use ($chunk, &$createdCount): void {
                foreach ($chunk as $row) {
                    TimeEntry::query()->create($row);
                    $createdCount++;
                }
            });
        }

        $this->cleanupCsvFromS3($csvPath);

        return redirect()
            ->route('time-entries.index')
            ->with('status', sprintf('Time entries import completed. Created: %d.', $createdCount));
    }

    /**
     * Resolves valid rows by downloading and re-parsing the CSV from S3.
     * The S3 path is submitted via a hidden form field — no session dependency.
     * Returns null if the path is missing, invalid, or the S3 file is gone.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function resolveImportRows(Request $request): ?array
    {
        $csvPath = $request->input('csv_path');

        if (! is_string($csvPath) || $csvPath === '') {
            return null;
        }

        // Restrict to the expected S3 prefix to prevent arbitrary file access.
        if (! str_starts_with($csvPath, 'imports/time-entries/')) {
            return null;
        }

        if (! Storage::disk('s3')->exists($csvPath)) {
            return null;
        }

        $csvContents = Storage::disk('s3')->get($csvPath);

        if (! is_string($csvContents) || $csvContents === '') {
            return null;
        }

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return null;
        }

        fwrite($handle, $csvContents);
        rewind($handle);

        $parsed = $this->parseFromHandle($handle);
        fclose($handle);

        return $parsed['valid_rows'];
    }

    private function cleanupCsvFromS3(string $csvPath): void
    {
        if (str_starts_with($csvPath, 'imports/time-entries/')) {
            Storage::disk('s3')->delete($csvPath);
        }
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

        return $this->parseFromPath($path);
    }

    /**
     * @return array{
     *     headers: array<int, string>,
     *     valid_rows: array<int, array<string, mixed>>,
     *     invalid_rows: array<int, array<string, mixed>>,
     *     missing_headers: array<int, string>
     * }
     */
    private function parseFromPath(string $path): array
    {
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

        $parsed = $this->parseFromHandle($handle);
        fclose($handle);

        request()->session()->put('time_entries_import_rows', $parsed['valid_rows']);

        return $parsed;
    }

    /**
     * @param  resource  $handle
     * @return array{
     *     headers: array<int, string>,
     *     valid_rows: array<int, array<string, mixed>>,
     *     invalid_rows: array<int, array<string, mixed>>,
     *     missing_headers: array<int, string>
     * }
     */
    private function parseFromHandle($handle): array
    {
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

            $stage = $this->resolveStage($mapped);
            $hasStageReference = $this->nullableString($mapped['stage_uuid'] ?? null)
                || $this->nullableString($mapped['stage_name'] ?? null);

            if ($hasStageReference && ! $stage) {
                $invalidRows[] = [
                    'line' => $line,
                    'errors' => ['Unable to resolve stage by stage_uuid or stage_name for the resolved project.'],
                    'raw' => $mapped,
                ];

                continue;
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
    private function resolveStage(array $mapped): ?ProjectStage
    {
        $stageUuid = $this->nullableString($mapped['stage_uuid'] ?? null);
        $stageName = $this->nullableString($mapped['stage_name'] ?? null);

        if ($stageUuid) {
            $stage = ProjectStage::query()
                ->where('uuid', $stageUuid)
                ->first();

            if ($stage) {
                return $stage;
            }
        }

        if ($stageName) {
            return ProjectStage::query()
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
