<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetricsService $dashboardMetricsService): View
    {
        return view('dashboard', [
            'dashboard' => $dashboardMetricsService->getDashboardData(),
        ]);
    }
}
