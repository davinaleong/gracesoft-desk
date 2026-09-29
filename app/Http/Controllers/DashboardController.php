<?php

namespace App\Http\Controllers;

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
        ]);
    }
}
