<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;

class ProjectReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $projectSummary = TimeEntry::query()
            ->join('projects', 'time_entries.project_id', '=', 'projects.id')
            ->whereBetween('time_entries.entry_date', [$fromDate, $toDate])
            ->select('projects.code', 'projects.name')
            ->selectRaw('COALESCE(SUM(time_entries.duration_minutes), 0) as duration_minutes')
            ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as billable_amount')
            ->groupBy('projects.id', 'projects.code', 'projects.name')
            ->orderByDesc('billable_amount')
            ->get();

        $stageSummary = TimeEntry::query()
            ->leftJoin('project_stages', 'time_entries.project_stage_id', '=', 'project_stages.id')
            ->whereBetween('time_entries.entry_date', [$fromDate, $toDate])
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
                'active_projects' => Project::query()->where('status', 'active')->count(),
                'total_hours' => round(((float) TimeEntry::query()->whereBetween('entry_date', [$fromDate, $toDate])->sum('duration_minutes')) / 60, 2),
                'total_billable' => round((float) TimeEntry::query()->whereBetween('entry_date', [$fromDate, $toDate])->sum('billable_amount'), 2),
                'total_stages' => ProjectStage::query()->count(),
            ],
        ];
    }
}
