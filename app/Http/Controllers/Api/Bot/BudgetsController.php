<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use App\Models\BudgetAlert;
use App\Services\BudgetMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class BudgetsController extends Controller
{
    /**
     * Projects past a budget threshold, plus alerts raised since `?since=` (ISO 8601, default 7 days ago)
     * so the Telegram assistant can push only what is new.
     */
    public function alerts(Request $request, BudgetMonitor $monitor): JsonResponse
    {
        try {
            $since = $request->filled('since') ? Carbon::parse($request->string('since')->toString()) : now()->subDays(7);
        } catch (Throwable) {
            throw ValidationException::withMessages(['since' => 'The since value must be an ISO 8601 date or datetime.']);
        }

        $alerts = BudgetAlert::query()
            ->with('project')
            ->where('triggered_at', '>=', $since)
            ->orderBy('triggered_at')
            ->orderBy('threshold')
            ->get();

        return response()->json([
            'since' => $since->toIso8601String(),
            'projects' => $monitor->atRisk()->map(fn (array $row): array => [
                'code' => $row['project']->code,
                'name' => $row['project']->name,
                'budget_type' => $row['project']->budget_type,
                'budget' => round((float) $row['project']->budget_value, 2),
                'used' => $row['used'],
                'percent' => $row['percent'],
                'url' => route('projects.show', $row['project']),
            ])->values(),
            'alerts' => $alerts->map(fn (BudgetAlert $alert): array => [
                'project' => $alert->project?->code,
                'threshold' => $alert->threshold,
                'budget_type' => $alert->budget_type,
                'budget' => round((float) $alert->budget_value, 2),
                'used' => round((float) $alert->used_value, 2),
                'triggered_at' => $alert->triggered_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
