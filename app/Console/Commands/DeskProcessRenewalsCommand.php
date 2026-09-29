<?php

namespace App\Console\Commands;

use App\Services\RenewalProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('desk:process-renewals')]
#[Description('Create pending expenses for services renewing today, advance renewal dates and send reminders; safe to re-run')]
class DeskProcessRenewalsCommand extends Command
{
    public function handle(RenewalProcessor $renewals): int
    {
        $created = $renewals->processDue();
        $reminded = $renewals->sendReminders();

        $this->info("Created {$created} pending expense(s); sent {$reminded} reminder(s).");

        return self::SUCCESS;
    }
}
