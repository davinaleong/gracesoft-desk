<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('desk:recalculate-billable-amounts')]
#[Description('One-time pass: recalculate billable_amount on all time entries using each project\'s current hourly rate')]
class DeskRecalculateBillableAmountsCommand extends Command
{
    public function handle(): int
    {
        $count = TimeEntry::withTrashed()->count();

        if ($count === 0) {
            $this->info('No time entries found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Recalculating billable amounts for {$count} time entry/entries...");

        Project::query()->each(function (Project $project): void {
            $hourlyRate = (float) $project->hourly_rate;

            // Non-billable entries → always 0
            DB::table('time_entries')
                ->where('project_id', $project->id)
                ->where('is_billable', false)
                ->update(['billable_amount' => 0]);

            if ($hourlyRate <= 0.0) {
                DB::table('time_entries')
                    ->where('project_id', $project->id)
                    ->where('is_billable', true)
                    ->update(['billable_amount' => 0]);

                return;
            }

            DB::table('time_entries')
                ->where('project_id', $project->id)
                ->where('is_billable', true)
                ->where('duration_minutes', '>', 0)
                ->update([
                    'billable_amount' => DB::raw("ROUND(duration_minutes / 60.0 * {$hourlyRate}, 2)"),
                ]);

            DB::table('time_entries')
                ->where('project_id', $project->id)
                ->where('is_billable', true)
                ->where('duration_minutes', 0)
                ->update(['billable_amount' => 0]);
        });

        $this->info('Billable amounts recalculated successfully.');

        return self::SUCCESS;
    }
}
