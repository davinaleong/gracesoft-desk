<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\BudgetMonitor;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboardService, BudgetMonitor $budgetMonitor): View
    {
        return view('dashboard', [
            'dashboard' => $dashboardService->build(),
            'budgetsAtRisk' => $budgetMonitor->atRisk(),
            'upcomingRenewals' => Service::query()->renewingWithin(30)->with('vendor')->orderBy('next_renewal_date')->get(),
        ]);
    }
}
