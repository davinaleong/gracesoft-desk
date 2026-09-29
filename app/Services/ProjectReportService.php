<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                'revenue_by_billing_model' => $this->revenueByBillingModel($fromDate, $toDate),
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

    /**
     * Invoiced revenue (issued and paid invoices, excl. GST) by the billing model of each line's project.
     * Lines with no project (manual extras) are grouped as "unassigned".
     *
     * @return array<string, float>
     */
    private function revenueByBillingModel(string $fromDate, string $toDate): array
    {
        $rows = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoin('projects', 'projects.id', '=', 'invoice_lines.project_id')
            ->whereIn('invoices.status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PAID])
            ->whereNull('invoices.deleted_at')
            ->whereDate('invoices.issue_date', '>=', $fromDate)
            ->whereDate('invoices.issue_date', '<=', $toDate)
            ->selectRaw("CASE WHEN invoice_lines.project_id IS NULL THEN 'unassigned' ELSE COALESCE(projects.billing_model, 'hourly') END as model")
            ->selectRaw('COALESCE(SUM(invoice_lines.amount), 0) as revenue')
            ->groupBy('model')
            ->pluck('revenue', 'model');

        $result = [];

        foreach ([...Project::BILLING_MODELS, 'unassigned'] as $model) {
            $result[$model] = round((float) ($rows[$model] ?? 0), 2);
        }

        return $result;
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
            Invoice::withTrashed()->count(),
            (string) (Invoice::withTrashed()->max('updated_at') ?? 'none'),
        ]));
    }
}
