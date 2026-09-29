<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportClientsCsvRequest;
use App\Models\Client;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientImportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = ['code', 'name', 'billing_email', 'address', 'tax_id', 'currency', 'default_hourly_rate', 'status', 'notes'];

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, self::TEMPLATE_HEADERS);
            fputcsv($handle, ['', 'Acme Pte Ltd', 'accounts@acme.example', '1 Example Road, Singapore 123456', '201912345K', 'SGD', '120.00', 'active', 'Leave code blank for new clients']);

            fclose($handle);
        }, 'clients-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('clients.import');
    }

    public function preview(ImportClientsCsvRequest $request): View
    {
        $previousPath = $request->session()->get('clients_import_csv_path');
        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk('s3')->delete($previousPath);
        }

        $file = $request->file('csv_file');
        $storagePath = 'imports/clients/'.Str::uuid().'.csv';
        Storage::disk('s3')->put($storagePath, file_get_contents($file->getRealPath()));
        $request->session()->put('clients_import_csv_path', $storagePath);

        $parsed = $this->parseCsv($file);

        $request->session()->put('clients_import_rows', $parsed['valid_rows']);

        return view('clients.import-preview', [
            'validRows' => $parsed['valid_rows'],
            'invalidRows' => $parsed['invalid_rows'],
            'missingHeaders' => $parsed['missing_headers'],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $rows = $request->session()->get('clients_import_rows', []);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('clients.import.create')
                ->with('status', 'No import data found. Upload and preview a CSV file first.');
        }

        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($rows, &$createdCount, &$updatedCount): void {
            foreach ($rows as $row) {
                $code = $row['code'] ?? null;
                unset($row['code']);

                $client = $code ? Client::withTrashed()->where('client_code', $code)->first() : null;

                if ($client) {
                    $client->fill($row);

                    if ($client->trashed()) {
                        $client->restore();
                    }

                    $client->save();
                    $updatedCount++;

                    continue;
                }

                Client::query()->create($row);
                $createdCount++;
            }
        });

        $csvPath = $request->session()->get('clients_import_csv_path');
        if (is_string($csvPath) && $csvPath !== '') {
            Storage::disk('s3')->delete($csvPath);
        }

        $request->session()->forget(['clients_import_rows', 'clients_import_csv_path']);

        return redirect()
            ->route('clients.index')
            ->with('status', sprintf('Clients import completed. Created: %d, Updated: %d.', $createdCount, $updatedCount));
    }

    /**
     * @return array{
     *     valid_rows: array<int, array<string, mixed>>,
     *     invalid_rows: array<int, array{line: int, errors: array<int, string>, raw: array<string, mixed>}>,
     *     missing_headers: array<int, string>
     * }
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath() ?: $file->getPathname(), 'rb');

        if ($handle === false) {
            return [
                'valid_rows' => [],
                'invalid_rows' => [['line' => 0, 'errors' => ['Unable to read uploaded CSV file.'], 'raw' => []]],
                'missing_headers' => [],
            ];
        }

        $headerRow = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), $headerRow);
        $missingHeaders = array_values(array_diff(['name'], $headers));
        $defaultCurrency = (string) (SystemSetting::query()->where('key', 'default_currency')->value('value') ?: 'SGD');

        $validRows = [];
        $invalidRows = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if ($row === [null] || $row === []) {
                continue;
            }

            /** @var array<string, mixed> $mapped */
            $mapped = array_combine($headers, array_slice(array_pad($row, count($headers), null), 0, count($headers))) ?: [];

            $payload = [
                'code' => $this->nullableString($mapped['code'] ?? null) !== null ? strtoupper(trim((string) $mapped['code'])) : null,
                'name' => trim((string) ($mapped['name'] ?? '')),
                'billing_email' => $this->nullableString($mapped['billing_email'] ?? null),
                'address' => $this->nullableString($mapped['address'] ?? null),
                'tax_id' => $this->nullableString($mapped['tax_id'] ?? null),
                'currency' => strtoupper($this->nullableString($mapped['currency'] ?? null) ?? $defaultCurrency),
                'default_hourly_rate' => $this->nullableString($mapped['default_hourly_rate'] ?? null),
                'status' => strtolower($this->nullableString($mapped['status'] ?? null) ?? 'active'),
                'notes' => $this->nullableString($mapped['notes'] ?? null),
            ];

            $validator = Validator::make($payload, [
                'code' => ['nullable', 'string', Rule::exists('clients', 'client_code')],
                'name' => ['required', 'string', 'max:255'],
                'billing_email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:2000'],
                'tax_id' => ['nullable', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3', 'alpha'],
                'default_hourly_rate' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
                'status' => ['required', Rule::in(Client::STATUSES)],
                'notes' => ['nullable', 'string'],
            ], [
                'code.exists' => 'Unknown client code. Leave code blank to create a new client.',
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

        return [
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'missing_headers' => $missingHeaders,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
