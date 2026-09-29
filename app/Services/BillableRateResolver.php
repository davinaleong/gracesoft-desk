<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\SystemSetting;

/**
 * Resolves the hourly rate a project bills at.
 *
 * Precedence: the project's own rate, then its client's default rate,
 * then the system default rate. A null or zero rate counts as "not set".
 */
class BillableRateResolver
{
    public function forProjectId(?int $projectId): float
    {
        if ($projectId === null) {
            return 0.0;
        }

        $project = Project::withTrashed()->find($projectId, ['id', 'client_id', 'hourly_rate', 'billing_model']);

        return $project ? $this->forProject($project) : 0.0;
    }

    public function forProject(Project $project): float
    {
        // Fixed-fee projects track hours as effort only: the fee is billed through milestones.
        if ($project->billing_model === Project::BILLING_FIXED_FEE) {
            return 0.0;
        }

        $projectRate = (float) $project->hourly_rate;

        if ($projectRate > 0) {
            return $projectRate;
        }

        if ($project->client_id !== null) {
            $clientRate = (float) Client::withTrashed()->whereKey($project->client_id)->value('default_hourly_rate');

            if ($clientRate > 0) {
                return $clientRate;
            }
        }

        return $this->systemDefault();
    }

    public function systemDefault(): float
    {
        return max(0.0, (float) SystemSetting::query()->where('key', 'default_hourly_rate')->value('value'));
    }
}
