<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Cache;

class ProjectReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $cacheKey = sprintf(
            'reports.projects.%s.%s.%s',
            $fromDate,
            $toDate,
            $this->projectReportCacheSignature()
        );

        $callback = function () use ($fromDate, $toDate): array {
            $projectSummary = TimeEntry::query()
                ->withinDateRange($fromDate, $toDate)
                ->join('projects', 'time_entries.project_id', '=', 'projects.id')
                ->select('projects.code', 'projects.uuid', 'projects.name')
                ->selectRaw('COALESCE(SUM(time_entries.duration_minutes), 0) as duration_minutes')
                ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as billable_amount')
                ->groupBy('projects.id', 'projects.code', 'projects.uuid', 'projects.name')
                ->orderByDesc('billable_amount')
                ->get();

            $stageSummary = TimeEntry::query()
                ->withinDateRange($fromDate, $toDate)
                ->leftJoin('project_stages', 'time_entries.project_stage_id', '=', 'project_stages.id')
                ->selectRaw("COALESCE(project_stages.name, 'No Stage') as stage_name")
                ->selectRaw('COALESCE(SUM(time_entries.duration_minutes), 0) as duration_minutes')
                ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as billable_amount')
                ->groupBy('stage_name')
                ->orderByDesc('billable_amount')
                ->get();

            return [
                'range' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'project_summary' => $projectSummary,
                'stage_summary' => $stageSummary,
                'totals' => [
                    'active_projects' => Project::query()->active()->count(),
                    'total_hours' => round(((float) TimeEntry::query()->withinDateRange($fromDate, $toDate)->sum('duration_minutes')) / 60, 2),
                    'total_billable' => round((float) TimeEntry::query()->withinDateRange($fromDate, $toDate)->sum('billable_amount'), 2),
                    'total_stages' => ProjectStage::query()->count(),
                ],
            ];
        };

        if (app()->environment('testing')) {
            return $callback();
        }

        return Cache::remember($cacheKey, now()->addMinutes(5), $callback);
    }

    private function projectReportCacheSignature(): string
    {
        $timeEntryCount = TimeEntry::query()->count();
        $latestTimeEntryUpdate = (string) (TimeEntry::query()->max('updated_at') ?? 'none');
        $projectCount = Project::query()->count();
        $latestProjectUpdate = (string) (Project::query()->max('updated_at') ?? 'none');

        return sha1(implode('|', [
            $timeEntryCount,
            $latestTimeEntryUpdate,
            $projectCount,
            $latestProjectUpdate,
        ]));
    }
}
