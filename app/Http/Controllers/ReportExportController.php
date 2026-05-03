<?php

namespace App\Http\Controllers;

use App\Services\FinanceReportService;
use App\Services\LedgerSummaryService;
use App\Services\ProjectReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        public FinanceReportService $financeReportService,
        public ProjectReportService $projectReportService,
        public LedgerSummaryService $ledgerSummaryService,
    ) {}

    public function finance(Request $request): StreamedResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $report = $this->financeReportService->build($fromDate, $toDate);

        return $this->csvResponse('finance-report.csv', [
            ['Transaction Code', 'Date', 'Type', 'Direction', 'Status', 'Category', 'Project', 'Net Amount'],
            ...collect($report['transactions'])->map(fn ($row): array => [
                $row->transaction_code,
                $row->transaction_date?->toDateString(),
                $row->type,
                $row->direction,
                $row->status,
                $row->category?->name,
                $row->project?->code,
                number_format((float) $row->net_amount, 2, '.', ''),
            ])->all(),
        ]);
    }

    public function projects(Request $request): StreamedResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $report = $this->projectReportService->build($fromDate, $toDate);

        return $this->csvResponse('project-report.csv', [
            ['Project Code', 'Project Name', 'Hours', 'Billable Amount'],
            ...collect($report['project_summary'])->map(fn ($row): array => [
                $row->code,
                $row->name,
                number_format(((int) $row->duration_minutes) / 60, 2, '.', ''),
                number_format((float) $row->billable_amount, 2, '.', ''),
            ])->all(),
        ]);
    }

    public function monthlySummary(Request $request): StreamedResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $report = $this->ledgerSummaryService->build($fromDate, $toDate);

        return $this->csvResponse('monthly-summary-report.csv', [
            ['Month', 'Income', 'Expense', 'Pending'],
            ...collect($report['monthly_summary'])->map(fn (array $row): array => [
                $row['month_label'],
                number_format((float) $row['income_total'], 2, '.', ''),
                number_format((float) $row['expense_total'], 2, '.', ''),
                number_format((float) $row['pending_total'], 2, '.', ''),
            ])->all(),
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function csvResponse(string $fileName, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        $fromDate = (string) $request->string('from', now()->startOfMonth()->toDateString());
        $toDate = (string) $request->string('to', now()->endOfMonth()->toDateString());

        return [$fromDate, $toDate];
    }
}
