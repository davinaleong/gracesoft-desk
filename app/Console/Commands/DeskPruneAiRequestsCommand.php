<?php

namespace App\Console\Commands;

use App\Models\AiRequest;
use App\Services\Ai\AiSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('desk:prune-ai-requests {--days= : Keep this many days of log (defaults to the AI settings retention)}')]
#[Description('Delete AI request log rows older than the retention period')]
class DeskPruneAiRequestsCommand extends Command
{
    public function handle(AiSettings $settings): int
    {
        $days = (int) ($this->option('days') ?: $settings->retentionDays());

        $deleted = AiRequest::query()->where('requested_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} AI request log row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
