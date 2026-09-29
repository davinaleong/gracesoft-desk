<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\RetainerBillingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('desk:draft-retainer-invoices {--month= : Month to bill as YYYY-MM (defaults to last month in the system timezone)}')]
#[Description('Draft each retainer project\'s monthly invoice, with overage lines; safe to re-run')]
class DeskDraftRetainerInvoicesCommand extends Command
{
    public function handle(RetainerBillingService $retainers): int
    {
        $period = (string) ($this->option('month') ?: $retainers->defaultPeriod());

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            $this->error('The --month option must look like 2026-09.');

            return self::INVALID;
        }

        $created = 0;

        Project::query()
            ->where('billing_model', Project::BILLING_RETAINER)
            ->with('client')
            ->each(function (Project $project) use ($retainers, $period, &$created): void {
                if ($project->client_id === null) {
                    $this->warn("{$project->code}: no client, skipped.");

                    return;
                }

                if ($retainers->draftFor($project, $period) !== null) {
                    $created++;
                    $this->line("{$project->code}: draft created for {$period}.");
                }
            });

        $this->info("Drafted {$created} retainer invoice(s) for {$period}.");

        return self::SUCCESS;
    }
}
