<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('desk:prelaunch-check {--seed-admin : Seed or update the production admin before checks} {--strict : Fail on any warning-grade check}')]
#[Description('Run launch-readiness checks for GraceSoft Desk environment and security hardening')]
class DeskPrelaunchCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('seed-admin')) {
            $this->seedAdmin();
        }

        $checks = [
            $this->check('APP_KEY is configured', filled(config('app.key'))),
            $this->check('APP_DEBUG is disabled', config('app.debug') === false),
            $this->check('APP_URL is configured', filled(config('app.url'))),
            $this->check('Session cookie is secure', (bool) config('session.secure') === true),
            $this->check('Session cookie is HTTP-only', (bool) config('session.http_only') === true),
            $this->check(
                'Session same-site policy is strict or lax',
                in_array((string) config('session.same_site'), ['strict', 'lax'], true)
            ),
            $this->check('Mail sender address is configured', filled(config('mail.from.address'))),
            $this->check('Queue connection is configured', filled(config('queue.default'))),
            $this->check(
                'Queue connection is not sync',
                (string) config('queue.default') !== 'sync',
                warning: true
            ),
            $this->check('Admin account exists', $this->adminUserExists()),
            $this->check('Backup directory is writable', $this->backupDirectoryWritable()),
            $this->check(
                'Admin temporary password is not default value',
                $this->adminTempPasswordNotDefault(),
                warning: true
            ),
        ];

        $this->newLine();
        $this->table(
            ['Check', 'Result'],
            array_map(
                fn (array $check): array => [
                    $check['label'],
                    $check['passed'] ? 'PASS' : ($check['warning'] ? 'WARN' : 'FAIL'),
                ],
                $checks
            )
        );

        $failed = collect($checks)->filter(fn (array $check): bool => ! $check['passed'] && ! $check['warning'])->count();
        $warnings = collect($checks)->filter(fn (array $check): bool => ! $check['passed'] && $check['warning'])->count();

        if ($failed > 0) {
            $this->error("Prelaunch checks failed: {$failed} failure(s), {$warnings} warning(s).");

            return self::FAILURE;
        }

        if ($warnings > 0 && $this->option('strict')) {
            $this->error("Prelaunch checks failed in strict mode: {$warnings} warning(s).");

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn("Prelaunch checks passed with {$warnings} warning(s).");

            return self::SUCCESS;
        }

        $this->info('Prelaunch checks passed with no issues.');

        return self::SUCCESS;
    }

    /**
     * @return array{label: string, passed: bool, warning: bool}
     */
    private function check(string $label, bool $passed, bool $warning = false): array
    {
        return [
            'label' => $label,
            'passed' => $passed,
            'warning' => $warning,
        ];
    }

    private function seedAdmin(): void
    {
        $this->callSilent('db:seed', ['--class' => AdminUserSeeder::class, '--no-interaction' => true]);
        $this->info('Admin user seed executed.');
    }

    private function adminUserExists(): bool
    {
        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@gracesoft.dev');

        return User::query()->where('email', $adminEmail)->exists();
    }

    private function backupDirectoryWritable(): bool
    {
        $backupPath = (string) env('DESK_BACKUP_PATH', storage_path('app/backups'));

        if (! File::exists($backupPath)) {
            File::ensureDirectoryExists($backupPath);
        }

        return File::isWritable($backupPath);
    }

    private function adminTempPasswordNotDefault(): bool
    {
        return (string) env('ADMIN_TEMP_PASSWORD', 'ChangeMe123!') !== 'ChangeMe123!';
    }
}
