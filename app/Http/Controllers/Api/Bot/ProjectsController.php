<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectsController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->with('latestTimeEntry.stage')
            ->orderBy('status')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'code' => $project->code,
                'name' => $project->name,
                'status' => $project->status,
                'current_stage' => $project->latestTimeEntry?->stage?->name,
            ]);

        return response()->json(['projects' => $projects]);
    }
}
