<?php

namespace App\Services;

class DashboardService
{
    public function __construct(private DashboardMetricsService $dashboardMetricsService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->dashboardMetricsService->getDashboardData();
    }
}
