<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProjectsCsvRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, ['code', 'name', 'status', 'description', 'starts_on', 'ends_on', 'is_billable']);
            fputcsv($handle, ['PRJ-001', 'Website Revamp', 'active', 'Q3 delivery scope', '2026-07-01', '2026-09-30', 'yes']);

            fclose($handle);
        }, 'projects-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('projects.import');
    }

    public function preview(ImportProjectsCsvRequest $request): View
    {
        $previousPath = $request->session()->get('projects_import_csv_path');
        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk('s3')->delete($previousPath);
        }

        $file = $request->file('csv_file');
        $storagePath = 'imports/projects/'.Str::uuid().'.csv';
        Storage::disk('s3')->put($storagePath, file_get_contents($file->getRealPath()));
        $request->session()->put('projects_import_csv_path', $storagePath);

        $parsed = $this->parseCsv($file);

        return view('projects.import-preview', [
            'headers' => $parsed['headers'],
            'validRows' => $parsed['valid_rows'],
            'invalidRows' => $parsed['invalid_rows'],
            'missingHeaders' => $parsed['missing_headers'],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $rows = $request->session()->get('projects_import_rows', []);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('projects.import.create')
                ->with('status', 'No import data found. Upload and preview a CSV file first.');
        }

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($rows as $row) {
            $action = $this->upsertProject($row);

            if ($action === 'created') {
                $createdCount++;
            }

            if ($action === 'updated') {
                $updatedCount++;
            }
        }

        $csvPath = $request->session()->get('projects_import_csv_path');
        if (is_string($csvPath) && $csvPath !== '') {
            Storage::disk('s3')->delete($csvPath);
        }

        $request->session()->forget(['projects_import_rows', 'projects_import_csv_path']);

        return redirect()
            ->route('projects.index')
            ->with('status', sprintf('Projects import completed. Created: %d, Updated: %d.', $createdCount, $updatedCount));
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

        $requiredHeaders = ['code', 'name'];
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

            $payload = [
                'code' => strtoupper(trim((string) ($mapped['code'] ?? ''))),
                'name' => trim((string) ($mapped['name'] ?? '')),
                'status' => trim((string) ($mapped['status'] ?? 'active')) ?: 'active',
                'description' => $this->nullableString($mapped['description'] ?? null),
                'starts_on' => $this->nullableString($mapped['starts_on'] ?? null),
                'ends_on' => $this->nullableString($mapped['ends_on'] ?? null),
                'is_billable' => $this->normalizeBoolean($mapped['is_billable'] ?? '1'),
            ];

            $validator = Validator::make($payload, [
                'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9\-]+$/'],
                'name' => ['required', 'string', 'max:255'],
                'status' => ['required', 'string', 'max:50'],
                'description' => ['nullable', 'string'],
                'starts_on' => ['nullable', 'date'],
                'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
                'is_billable' => ['required', 'boolean'],
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

        request()->session()->put('projects_import_rows', $validRows);

        return [
            'headers' => $headers,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'missing_headers' => $missingHeaders,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertProject(array $row): string
    {
        $project = Project::withTrashed()->where('code', $row['code'])->first();

        if ($project) {
            $project->fill($row);

            if ($project->trashed()) {
                $project->restore();
            }

            $project->save();

            return 'updated';
        }

        Project::query()->create($row);

        return 'created';
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }
}
