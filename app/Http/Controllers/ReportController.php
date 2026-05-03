<?php

namespace App\Http\Controllers;

use App\Services\FinanceReportService;
use App\Services\LedgerSummaryService;
use App\Services\ProjectReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        public FinanceReportService $financeReportService,
        public ProjectReportService $projectReportService,
        public LedgerSummaryService $ledgerSummaryService,
    ) {}

    public function finance(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.finance', [
            'report' => $this->financeReportService->build($fromDate, $toDate),
        ]);
    }

    public function projects(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.projects', [
            'report' => $this->projectReportService->build($fromDate, $toDate),
        ]);
    }

    public function monthlySummary(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.monthly-summary', [
            'report' => $this->ledgerSummaryService->build($fromDate, $toDate),
        ]);
    }

    public function printFinance(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.print', [
            'title' => 'Finance Report',
            'reportType' => 'finance',
            'report' => $this->financeReportService->build($fromDate, $toDate),
        ]);
    }

    public function printProjects(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.print', [
            'title' => 'Project Report',
            'reportType' => 'projects',
            'report' => $this->projectReportService->build($fromDate, $toDate),
        ]);
    }

    public function printMonthlySummary(Request $request): View
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        return view('reports.print', [
            'title' => 'Monthly Summary Report',
            'reportType' => 'monthly',
            'report' => $this->ledgerSummaryService->build($fromDate, $toDate),
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
