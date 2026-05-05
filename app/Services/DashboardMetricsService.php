<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $cacheKey = sprintf('dashboard.metrics.%s', $this->dashboardCacheSignature());

        $callback = function (): array {
            $activeProjects = Project::query()->active()->count();
            $totalDurationMinutes = (int) TimeEntry::query()->sum('duration_minutes');
            $totalBillableValue = (float) TimeEntry::query()->sum('billable_amount');

            $currentMonthStart = now()->startOfMonth()->toDateString();
            $currentMonthEnd = now()->endOfMonth()->toDateString();
            $netCashflowThisMonth = (float) (Transaction::query()
                ->completed()
                ->withinDateRange($currentMonthStart, $currentMonthEnd)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN net_amount ELSE -net_amount END), 0) as net_total")
                ->value('net_total') ?? 0);

            return [
                'kpis' => [
                    'active_projects' => $activeProjects,
                    'total_logged_hours' => round($totalDurationMinutes / 60, 2),
                    'total_billable_value' => round($totalBillableValue, 2),
                    'net_cashflow_this_month' => round($netCashflowThisMonth, 2),
                ],
                'monthly_cashflow' => $this->monthlyCashflowSeries(),
                'expense_breakdown' => $this->breakdownByCategory('out'),
                'income_breakdown' => $this->breakdownByCategory('in'),
                'pending_transactions' => $this->pendingTransactionsRows(),
                'project_overview' => $this->projectOverviewRows(),
                'billable_by_project' => $this->billableByProject(),
                'billable_by_stage' => $this->billableByStage(),
            ];
        };

        if (app()->environment('testing')) {
            return $callback();
        }

        return Cache::remember($cacheKey, now()->addMinutes(5), $callback);
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function monthlyCashflowSeries(): array
    {
        $monthStart = now()->startOfMonth()->subMonths(5);
        $monthEnd = now()->endOfMonth();
        $monthKeyExpression = $this->monthKeyExpression();

        $groupedRows = Transaction::query()
            ->completed()
            ->withinDateRange($monthStart->toDateString(), $monthEnd->toDateString())
            ->selectRaw($monthKeyExpression.' as month_key')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN net_amount ELSE -net_amount END), 0) as net_total")
            ->groupByRaw($monthKeyExpression)
            ->get();

        $groupedValues = $groupedRows
            ->mapWithKeys(fn ($row): array => [
                (string) $row->month_key => (float) $row->net_total,
            ])
            ->all();

        $labels = [];
        $values = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $monthStart->copy()->addMonths($offset);
            $monthKey = $month->format('Y-m');

            $labels[] = $month->format('M Y');
            $values[] = round((float) Arr::get($groupedValues, $monthKey, 0), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, duration_minutes: int, billable_amount: float}>
     */
    private function projectOverviewRows(): array
    {
        return Project::query()
            ->leftJoin('time_entries', 'projects.id', '=', 'time_entries.project_id')
            ->select('projects.code', 'projects.name')
            ->selectRaw('COALESCE(SUM(time_entries.duration_minutes), 0) as duration_minutes')
            ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as billable_amount')
            ->groupBy('projects.id', 'projects.code', 'projects.name')
            ->orderByDesc('billable_amount')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'duration_minutes' => (int) $row->duration_minutes,
                'billable_amount' => round((float) $row->billable_amount, 2),
            ])
            ->all();
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function breakdownByCategory(string $direction): array
    {
        $rows = Transaction::query()
            ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
            ->selectRaw("COALESCE(transaction_categories.name, 'Uncategorized') as label")
            ->selectRaw('COALESCE(SUM(transactions.net_amount), 0) as total_amount')
            ->completed()
            ->direction($direction)
            ->withinDateRange(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->groupBy('label')
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get();

        return [
            'labels' => $rows->pluck('label')->map(fn ($value): string => (string) $value)->all(),
            'values' => $rows->pluck('total_amount')->map(fn ($value): float => round((float) $value, 2))->all(),
        ];
    }

    /**
     * @return array<int, array{code: string, date: string, direction: string, net_amount: float, status: string}>
     */
    private function pendingTransactionsRows(): array
    {
        return Transaction::query()
            ->pending()
            ->latest('transaction_date')
            ->limit(6)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'code' => (string) $transaction->transaction_code,
                'date' => (string) $transaction->transaction_date?->toDateString(),
                'direction' => (string) $transaction->direction,
                'net_amount' => round((float) $transaction->net_amount, 2),
                'status' => (string) $transaction->status,
            ])
            ->all();
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function billableByProject(): array
    {
        $rows = TimeEntry::query()
            ->join('projects', 'time_entries.project_id', '=', 'projects.id')
            ->select('projects.code')
            ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as total_amount')
            ->groupBy('projects.id', 'projects.code')
            ->orderByDesc('total_amount')
            ->limit(6)
            ->get();

        return [
            'labels' => $rows->pluck('code')->map(fn ($value): string => (string) $value)->all(),
            'values' => $rows->pluck('total_amount')->map(fn ($value): float => round((float) $value, 2))->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function billableByStage(): array
    {
        $rows = TimeEntry::query()
            ->leftJoin('project_stages', 'time_entries.project_stage_id', '=', 'project_stages.id')
            ->selectRaw("COALESCE(project_stages.name, 'No Stage') as label")
            ->selectRaw('COALESCE(SUM(time_entries.billable_amount), 0) as total_amount')
            ->groupBy('label')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('label')->map(fn ($value): string => (string) $value)->all(),
            'values' => $rows->pluck('total_amount')->map(fn ($value): float => round((float) $value, 2))->all(),
        ];
    }

    private function dashboardCacheSignature(): string
    {
        return sha1(implode('|', [
            Project::query()->count(),
            (string) (Project::query()->max('updated_at') ?? 'none'),
            TimeEntry::query()->count(),
            (string) (TimeEntry::query()->max('updated_at') ?? 'none'),
            Transaction::query()->count(),
            (string) (Transaction::query()->max('updated_at') ?? 'none'),
        ]));
    }

    private function monthKeyExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', transaction_date)";
        }

        return "DATE_FORMAT(transaction_date, '%Y-%m')";
    }
}
