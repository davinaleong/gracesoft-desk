<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Services\WeeklyTimesheetService;
use Illuminate\Http\JsonResponse;

class TimesheetController extends Controller
{
    public function pending(WeeklyTimesheetService $timesheet): JsonResponse
    {
        $weekStart = $timesheet->weekStart();

        $byProject = CommitTimeEntry::query()
            ->pending()
            ->selectRaw('project_id, COUNT(*) as pending_count')
            ->groupBy('project_id')
            ->pluck('pending_count', 'project_id');

        $projects = Project::query()->whereIn('id', $byProject->keys())->get(['id', 'code', 'name'])->keyBy('id');

        return response()->json([
            'pending_count' => (int) $byProject->sum(),
            'this_week_count' => $timesheet->pendingForWeek($weekStart)->count(),
            'oldest_pending_at' => CommitTimeEntry::query()->pending()->orderBy('committed_at')->first()?->committed_at?->toIso8601String(),
            'week' => ['from' => $weekStart->toDateString(), 'to' => $weekStart->addDays(6)->toDateString()],
            'projects' => $byProject->map(fn ($count, $projectId): array => [
                'code' => $projects->get($projectId)?->code,
                'name' => $projects->get($projectId)?->name,
                'pending_count' => (int) $count,
            ])->values(),
            'url' => route('timesheet.index'),
        ]);
    }
}
