<?php

namespace App\Http\Controllers;

use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Services\CommitStageMatcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PendingCommitController extends Controller
{
    public function index(Project $project): View
    {
        $commits = CommitTimeEntry::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->latest('committed_at')
            ->paginate(25);

        return view('projects.pending-commits.index', [
            'project' => $project,
            'commits' => $commits,
        ]);
    }

    public function create(Project $project, CommitTimeEntry $commit): View
    {
        abort_unless($commit->project_id === $project->id, 404);

        $matcher = new CommitStageMatcherService;
        $suggestedStage = $matcher->match($commit->message, $commit->branch ?? '')
            ?? $commit->aiSuggestedStage;
        $stages = ProjectStage::query()->orderBy('sort_order')->get();

        return view('projects.pending-commits.convert', [
            'project' => $project,
            'commit' => $commit,
            'suggestedStage' => $suggestedStage,
            'stages' => $stages,
        ]);
    }

    public function store(Request $request, Project $project, CommitTimeEntry $commit): RedirectResponse
    {
        abort_unless($commit->project_id === $project->id, 404);

        $validated = $request->validate([
            'project_stage_uuid' => ['nullable', 'exists:project_stages,uuid'],
            'entry_date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'is_billable' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['duration_minutes'] = CommitStageMatcherService::snapDuration(
            (int) $validated['duration_minutes']
        );

        $stageId = null;
        if ($validated['project_stage_uuid']) {
            $stageId = ProjectStage::where('uuid', $validated['project_stage_uuid'])->value('id');
        }

        $timeEntry = TimeEntry::create([
            'project_id' => $project->id,
            'project_stage_id' => $stageId,
            'user_id' => Auth::id(),
            'entry_date' => $validated['entry_date'],
            'duration_minutes' => $validated['duration_minutes'],
            'is_billable' => (bool) $validated['is_billable'],
            'notes' => $validated['notes'] ?? $commit->message,
        ]);

        $commit->update([
            'status' => 'approved',
            'converted_time_entry_id' => $timeEntry->id,
        ]);

        return redirect()
            ->route('projects.pending-commits.index', $project)
            ->with('status', 'commit-converted');
    }
}
