<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportTransactionsCsvRequest;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Support\TransactionIntegrity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, ['transaction_code', 'account_uuid', 'account_code', 'category_uuid', 'category_slug', 'payment_method_uuid', 'payment_method_slug', 'project_uuid', 'project_code', 'type', 'direction', 'status', 'transaction_date', 'reference', 'description', 'amount', 'gst_amount']);
            fputcsv($handle, ['TRX-001', '', 'BANK-001', '', 'software', '', 'bank-transfer', '', 'PRJ-001', 'expense', 'out', 'completed', '2026-07-20', 'INV-2026-001', 'Subscription renewal', '240.00', '20.00']);

            fclose($handle);
        }, 'transactions-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        return view('transactions.import');
    }

    public function preview(ImportTransactionsCsvRequest $request): View
    {
        $parsed = $this->parseCsv($request->file('csv_file'));

        return view('transactions.import-preview', [
            'headers' => $parsed['headers'],
            'validRows' => $parsed['valid_rows'],
            'invalidRows' => $parsed['invalid_rows'],
            'missingHeaders' => $parsed['missing_headers'],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $rows = $request->session()->get('transactions_import_rows', []);

        if (! is_array($rows) || $rows === []) {
            return redirect()
                ->route('transactions.import.create')
                ->with('status', 'No import data found. Upload and preview a CSV file first.');
        }

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($rows as $row) {
            $action = $this->upsertTransaction($row);

            if ($action === 'created') {
                $createdCount++;
            }

            if ($action === 'updated') {
                $updatedCount++;
            }
        }

        $request->session()->forget('transactions_import_rows');

        return redirect()
            ->route('transactions.index')
            ->with('status', sprintf('Transactions import completed. Created: %d, Updated: %d.', $createdCount, $updatedCount));
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

        $requiredHeaders = ['account_code', 'type', 'direction', 'status', 'transaction_date', 'amount', 'gst_amount'];
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

            $account = $this->resolveAccount($mapped);

            if (! $account) {
                $invalidRows[] = [
                    'line' => $line,
                    'errors' => ['Unable to resolve account by account_uuid or account_code.'],
                    'raw' => $mapped,
                ];

                continue;
            }

            $category = $this->resolveCategory($mapped);

            if (($mapped['category_uuid'] ?? null) || ($mapped['category_slug'] ?? null) || ($mapped['category_name'] ?? null)) {
                if (! $category) {
                    $invalidRows[] = [
                        'line' => $line,
                        'errors' => ['Unable to resolve transaction category by category_uuid, category_slug, or category_name.'],
                        'raw' => $mapped,
                    ];

                    continue;
                }
            }

            $paymentMethod = $this->resolvePaymentMethod($mapped);

            if (($mapped['payment_method_uuid'] ?? null) || ($mapped['payment_method_slug'] ?? null) || ($mapped['payment_method_name'] ?? null)) {
                if (! $paymentMethod) {
                    $invalidRows[] = [
                        'line' => $line,
                        'errors' => ['Unable to resolve payment method by payment_method_uuid, payment_method_slug, or payment_method_name.'],
                        'raw' => $mapped,
                    ];

                    continue;
                }
            }

            $project = $this->resolveProject($mapped);

            if (($mapped['project_uuid'] ?? null) || ($mapped['project_code'] ?? null)) {
                if (! $project) {
                    $invalidRows[] = [
                        'line' => $line,
                        'errors' => ['Unable to resolve project by project_uuid or project_code.'],
                        'raw' => $mapped,
                    ];

                    continue;
                }
            }

            $amount = $this->nullableFloat($mapped['amount'] ?? null) ?? 0;
            $gstAmount = $this->nullableFloat($mapped['gst_amount'] ?? null) ?? 0;

            $payload = [
                'transaction_code' => $this->normalizeCode($mapped['transaction_code'] ?? null),
                'account_id' => $account->id,
                'transaction_category_id' => $category?->id,
                'payment_method_id' => $paymentMethod?->id,
                'project_id' => $project?->id,
                'type' => $this->normalizeEnum($mapped['type'] ?? null),
                'direction' => $this->normalizeEnum($mapped['direction'] ?? null),
                'status' => $this->normalizeEnum($mapped['status'] ?? null),
                'transaction_date' => $this->nullableString($mapped['transaction_date'] ?? null),
                'reference' => $this->nullableString($mapped['reference'] ?? null),
                'description' => $this->nullableString($mapped['description'] ?? null),
                'amount' => $amount,
                'gst_amount' => $gstAmount,
                'net_amount' => max(0, round($amount - $gstAmount, 2)),
            ];

            $validator = Validator::make($payload, [
                'transaction_code' => ['nullable', 'string', 'max:255'],
                'account_id' => ['required', 'integer', 'exists:accounts,id'],
                'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
                'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
                'project_id' => ['nullable', 'integer', 'exists:projects,id'],
                'type' => ['required', 'in:income,expense,transfer'],
                'direction' => ['required', 'in:in,out'],
                'status' => ['required', 'in:pending,completed,void'],
                'transaction_date' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'amount' => ['required', 'numeric', 'min:0'],
                'gst_amount' => ['required', 'numeric', 'min:0'],
                'net_amount' => ['required', 'numeric', 'min:0'],
            ]);

            $validator->after(function ($validator) use ($payload): void {
                $errors = TransactionIntegrity::validate($payload);

                foreach ($errors as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            });

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

        request()->session()->put('transactions_import_rows', $validRows);

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
    private function upsertTransaction(array $row): string
    {
        $transactionCode = $this->normalizeCode($row['transaction_code'] ?? null);

        $payload = $row;
        unset($payload['transaction_code']);

        if ($transactionCode) {
            $transaction = Transaction::query()->where('transaction_code', $transactionCode)->first();

            if ($transaction) {
                $transaction->update($payload);

                return 'updated';
            }

            $payload['transaction_code'] = $transactionCode;
        }

        Transaction::query()->create($payload);

        return 'created';
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveAccount(array $mapped): ?Account
    {
        $accountUuid = $this->nullableString($mapped['account_uuid'] ?? null);
        $accountCode = strtoupper((string) $this->nullableString($mapped['account_code'] ?? null));

        if ($accountUuid) {
            $account = Account::query()->where('uuid', $accountUuid)->first();

            if ($account) {
                return $account;
            }
        }

        if ($accountCode) {
            return Account::query()->where('code', $accountCode)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveCategory(array $mapped): ?TransactionCategory
    {
        $categoryUuid = $this->nullableString($mapped['category_uuid'] ?? null);
        $categorySlug = $this->nullableString($mapped['category_slug'] ?? null);
        $categoryName = $this->nullableString($mapped['category_name'] ?? null);

        if ($categoryUuid) {
            $category = TransactionCategory::query()->where('uuid', $categoryUuid)->first();

            if ($category) {
                return $category;
            }
        }

        if ($categorySlug) {
            $category = TransactionCategory::query()->where('slug', $categorySlug)->first();

            if ($category) {
                return $category;
            }
        }

        if ($categoryName) {
            return TransactionCategory::query()->where('name', $categoryName)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolvePaymentMethod(array $mapped): ?PaymentMethod
    {
        $paymentMethodUuid = $this->nullableString($mapped['payment_method_uuid'] ?? null);
        $paymentMethodSlug = $this->nullableString($mapped['payment_method_slug'] ?? null);
        $paymentMethodName = $this->nullableString($mapped['payment_method_name'] ?? null);

        if ($paymentMethodUuid) {
            $paymentMethod = PaymentMethod::query()->where('uuid', $paymentMethodUuid)->first();

            if ($paymentMethod) {
                return $paymentMethod;
            }
        }

        if ($paymentMethodSlug) {
            $paymentMethod = PaymentMethod::query()->where('slug', $paymentMethodSlug)->first();

            if ($paymentMethod) {
                return $paymentMethod;
            }
        }

        if ($paymentMethodName) {
            return PaymentMethod::query()->where('name', $paymentMethodName)->first();
        }

        return null;
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

    private function normalizeCode(mixed $value): ?string
    {
        $normalized = strtoupper((string) $this->nullableString($value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeEnum(mixed $value): ?string
    {
        $normalized = strtolower((string) $this->nullableString($value));

        return $normalized === '' ? null : $normalized;
    }
}
