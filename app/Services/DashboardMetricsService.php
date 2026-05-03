<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class DashboardMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $activeProjects = Project::query()->where('status', 'active')->count();
        $totalDurationMinutes = (int) TimeEntry::query()->sum('duration_minutes');
        $totalBillableValue = (float) TimeEntry::query()->sum('billable_amount');

        $currentMonthStart = now()->startOfMonth()->toDateString();
        $netCashflowThisMonth = (float) Transaction::query()
            ->where('status', 'completed')
            ->whereDate('transaction_date', '>=', $currentMonthStart)
            ->get()
            ->sum(fn (Transaction $transaction): float => $transaction->direction === 'in'
                ? (float) $transaction->net_amount
                : -1 * (float) $transaction->net_amount);

        return [
            'kpis' => [
                'active_projects' => $activeProjects,
                'total_logged_hours' => round($totalDurationMinutes / 60, 2),
                'total_billable_value' => round($totalBillableValue, 2),
                'net_cashflow_this_month' => round($netCashflowThisMonth, 2),
            ],
            'monthly_cashflow' => $this->monthlyCashflowSeries(),
            'project_overview' => $this->projectOverviewRows(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function monthlyCashflowSeries(): array
    {
        $monthStart = now()->startOfMonth()->subMonths(5);
        $monthEnd = now()->endOfMonth();

        $transactions = Transaction::query()
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $groupedValues = $transactions
            ->groupBy(fn (Transaction $transaction): string => $transaction->transaction_date?->format('Y-m') ?? now()->format('Y-m'))
            ->map(fn (Collection $rows): float => $rows->sum(fn (Transaction $row): float => $row->direction === 'in'
                ? (float) $row->net_amount
                : -1 * (float) $row->net_amount));

        $labels = [];
        $values = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $monthStart->copy()->addMonths($offset);
            $monthKey = $month->format('Y-m');

            $labels[] = $month->format('M Y');
            $values[] = round((float) ($groupedValues[$monthKey] ?? 0), 2);
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
}
