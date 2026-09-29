<?php

namespace App\Console\Commands;

use App\Mail\PendingCommitsReminderMail;
use App\Models\CommitTimeEntry;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('desk:timesheet-reminder')]
#[Description('Email a reminder when pending commits are waiting to be turned into time entries')]
class DeskTimesheetReminderCommand extends Command
{
    public function handle(): int
    {
        $pendingCount = CommitTimeEntry::query()->pending()->count();

        if ($pendingCount === 0) {
            $this->info('No pending commits. No reminder sent.');

            return self::SUCCESS;
        }

        $recipients = User::query()->pluck('email')->filter()->all();

        if ($recipients === []) {
            $this->warn('No user to remind.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new PendingCommitsReminderMail($pendingCount));

        $this->info("Reminder sent for {$pendingCount} pending commit(s).");

        return self::SUCCESS;
    }
}
