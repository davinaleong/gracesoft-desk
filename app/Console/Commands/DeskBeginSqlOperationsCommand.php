<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('desk:begin-sql-operations {--force : Re-run even if SQL-first operations were already started}')]
#[Description('Begin SQL-first operations mode for GraceSoft Desk')]
class DeskBeginSqlOperationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $alreadyStartedAt = SystemSetting::query()
            ->where('key', 'sql_operations_started_at')
            ->value('value');

        if (filled($alreadyStartedAt) && ! $this->option('force')) {
            $this->warn('SQL-first operations were already started at '.$alreadyStartedAt.'.');
            $this->line('Re-run with --force to refresh operational start markers.');

            return self::SUCCESS;
        }

        SystemSetting::upsertValues([
            'archive_mode' => false,
            'operations_mode' => 'sql-first',
            'sql_operations_started_at' => now()->toDateTimeString(),
        ]);

        $this->info('SQL-first operations mode is now active.');
        $this->line('- archive_mode: OFF');
        $this->line('- operations_mode: sql-first');

        return self::SUCCESS;
    }
}
