<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('desk:import-live-data {--projects= : Absolute path to projects CSV exported from Excel} {--time-entries= : Absolute path to time entries CSV exported from Excel} {--transactions= : Absolute path to transactions CSV exported from Excel}')]
#[Description('Import live operational data from Excel-exported CSV files into SQL tables')]
class DeskImportLiveDataCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projectsPath = $this->option('projects');
        $timeEntriesPath = $this->option('time-entries');
        $transactionsPath = $this->option('transactions');

        if (! $projectsPath && ! $timeEntriesPath && ! $transactionsPath) {
            $this->error('Provide at least one CSV path using --projects, --time-entries, or --transactions.');

            return self::FAILURE;
        }

        $imported = [
            'projects' => 0,
            'time_entries' => 0,
            'transactions' => 0,
        ];

        if (is_string($projectsPath)) {
            $imported['projects'] = $this->importProjects($projectsPath);
        }

        if (is_string($timeEntriesPath)) {
            $imported['time_entries'] = $this->importTimeEntries($timeEntriesPath);
        }

        if (is_string($transactionsPath)) {
            $imported['transactions'] = $this->importTransactions($transactionsPath);
        }

        $this->newLine();
        $this->table('Dataset', [
            ['Projects', $imported['projects']],
            ['Time Entries', $imported['time_entries']],
            ['Transactions', $imported['transactions']],
        ]);

        $this->info('Live CSV import completed.');

        return self::SUCCESS;
    }

    private function assertCsvReadable(string $path): void
    {
        if (! File::exists($path)) {
            throw new \RuntimeException('CSV file not found: '.$path);
        }

        if (! File::isFile($path)) {
            throw new \RuntimeException('Path is not a file: '.$path);
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readCsv(string $path): array
    {
        $this->assertCsvReadable($path);

        $handle = fopen($path, 'rb');

        if (! is_resource($handle)) {
            throw new \RuntimeException('Unable to open CSV file: '.$path);
        }

        $header = fgetcsv($handle) ?: [];
        $normalizedHeader = array_map(fn ($value): string => strtolower(trim((string) $value)), $header);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $padded = array_pad($row, count($normalizedHeader), null);
            /** @var array<string, string|null> $mapped */
            $mapped = array_combine($normalizedHeader, $padded) ?: [];
            $rows[] = $mapped;
        }

        fclose($handle);

        return $rows;
    }

    private function importProjects(string $path): int
    {
        $count = 0;

        foreach ($this->readCsv($path) as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));

            if ($code === '') {
                continue;
            }

            Project::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => trim((string) ($row['name'] ?? 'Untitled Project')),
                    'status' => trim((string) ($row['status'] ?? 'active')),
                    'description' => $this->nullable($row['description'] ?? null),
                    'starts_on' => $this->nullable($row['starts_on'] ?? null),
                    'ends_on' => $this->nullable($row['ends_on'] ?? null),
                    'is_billable' => $this->normalizeBoolean($row['is_billable'] ?? '1'),
                ]
            );

            $count++;
        }

        return $count;
    }

    private function importTimeEntries(string $path): int
    {
        $count = 0;
        $defaultUserId = User::query()->value('id');

        foreach ($this->readCsv($path) as $row) {
            $project = $this->resolveProject($row['project_uuid'] ?? null, $row['project_code'] ?? null);

            if (! $project) {
                continue;
            }

            $stage = $this->resolveStage($project, $row['stage_uuid'] ?? null, $row['stage_name'] ?? null);
            $duration = max(1, (int) ($row['duration_minutes'] ?? 0));

            TimeEntry::query()->create([
                'project_id' => $project->id,
                'project_stage_id' => $stage?->id,
                'user_id' => $defaultUserId,
                'entry_date' => trim((string) ($row['entry_date'] ?? now()->toDateString())),
                'duration_minutes' => $duration,
                'is_billable' => $this->normalizeBoolean($row['is_billable'] ?? '1'),
                'hourly_rate' => (float) ($row['hourly_rate'] ?? 0),
                'notes' => $this->nullable($row['notes'] ?? null),
            ]);

            $count++;
        }

        return $count;
    }

    private function importTransactions(string $path): int
    {
        $count = 0;

        foreach ($this->readCsv($path) as $row) {
            $transactionCode = strtoupper(trim((string) ($row['transaction_code'] ?? '')));

            if ($transactionCode === '') {
                continue;
            }

            $account = $this->resolveAccount($row['account_uuid'] ?? null, $row['account_code'] ?? null);
            $category = $this->resolveCategory($row['category_uuid'] ?? null, $row['category_slug'] ?? null);
            $paymentMethod = $this->resolvePaymentMethod($row['payment_method_uuid'] ?? null, $row['payment_method_slug'] ?? null);
            $project = $this->resolveProject($row['project_uuid'] ?? null, $row['project_code'] ?? null);

            if (! $account || ! $category || ! $paymentMethod) {
                continue;
            }

            Transaction::query()->updateOrCreate(
                ['transaction_code' => $transactionCode],
                [
                    'account_id' => $account->id,
                    'transaction_category_id' => $category->id,
                    'payment_method_id' => $paymentMethod->id,
                    'project_id' => $project?->id,
                    'type' => trim((string) ($row['type'] ?? 'expense')),
                    'direction' => trim((string) ($row['direction'] ?? 'out')),
                    'status' => trim((string) ($row['status'] ?? 'pending')),
                    'transaction_date' => trim((string) ($row['transaction_date'] ?? now()->toDateString())),
                    'reference' => $this->nullable($row['reference'] ?? null),
                    'description' => $this->nullable($row['description'] ?? null),
                    'amount' => (float) ($row['amount'] ?? 0),
                    'gst_amount' => (float) ($row['gst_amount'] ?? 0),
                ]
            );

            $count++;
        }

        return $count;
    }

    private function resolveProject(?string $uuid, ?string $code): ?Project
    {
        $trimmedUuid = trim((string) $uuid);
        $trimmedCode = strtoupper(trim((string) $code));

        if ($trimmedUuid !== '') {
            $byUuid = Project::query()->where('uuid', $trimmedUuid)->first();

            if ($byUuid) {
                return $byUuid;
            }
        }

        if ($trimmedCode !== '') {
            return Project::query()->where('code', $trimmedCode)->first();
        }

        return null;
    }

    private function resolveStage(Project $project, ?string $uuid, ?string $name): ?ProjectStage
    {
        $trimmedUuid = trim((string) $uuid);
        $trimmedName = trim((string) $name);

        if ($trimmedUuid !== '') {
            $byUuid = ProjectStage::query()->where('uuid', $trimmedUuid)->first();

            if ($byUuid) {
                return $byUuid;
            }
        }

        if ($trimmedName !== '') {
            return ProjectStage::query()
                ->where('project_id', $project->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($trimmedName)])
                ->first();
        }

        return null;
    }

    private function resolveAccount(?string $uuid, ?string $code): ?Account
    {
        $trimmedUuid = trim((string) $uuid);
        $trimmedCode = strtoupper(trim((string) $code));

        if ($trimmedUuid !== '') {
            $byUuid = Account::query()->where('uuid', $trimmedUuid)->first();

            if ($byUuid) {
                return $byUuid;
            }
        }

        if ($trimmedCode !== '') {
            return Account::query()->where('code', $trimmedCode)->first();
        }

        return null;
    }

    private function resolveCategory(?string $uuid, ?string $slug): ?TransactionCategory
    {
        $trimmedUuid = trim((string) $uuid);
        $trimmedSlug = strtolower(trim((string) $slug));

        if ($trimmedUuid !== '') {
            $byUuid = TransactionCategory::query()->where('uuid', $trimmedUuid)->first();

            if ($byUuid) {
                return $byUuid;
            }
        }

        if ($trimmedSlug !== '') {
            return TransactionCategory::query()->where('slug', $trimmedSlug)->first();
        }

        return null;
    }

    private function resolvePaymentMethod(?string $uuid, ?string $slug): ?PaymentMethod
    {
        $trimmedUuid = trim((string) $uuid);
        $trimmedSlug = strtolower(trim((string) $slug));

        if ($trimmedUuid !== '') {
            $byUuid = PaymentMethod::query()->where('uuid', $trimmedUuid)->first();

            if ($byUuid) {
                return $byUuid;
            }
        }

        if ($trimmedSlug !== '') {
            return PaymentMethod::query()->where('slug', $trimmedSlug)->first();
        }

        return null;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function nullable(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
