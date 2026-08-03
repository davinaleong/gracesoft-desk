<?php

namespace App\Http\Controllers;

use App\Jobs\SummarizeSquashedCommits;
use App\Models\CommitTimeEntry;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Services\CommitStageMatcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

        $suggestedBatches = CommitTimeEntry::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->where('from_large_batch', true)
            ->orderBy('committed_at')
            ->get()
            ->groupBy('push_batch_uuid')
            ->filter(fn ($group) => $group->count() >= 2);

        return view('projects.pending-commits.index', [
            'project' => $project,
            'commits' => $commits,
            'suggestedBatches' => $suggestedBatches,
        ]);
    }

    public function create(Project $project, CommitTimeEntry $commit): View
    {
        abort_unless($commit->project_id === $project->id, 404);

        $squashedCommits = CommitTimeEntry::query()
            ->where('squashed_into', $commit->id)
            ->orderBy('committed_at')
            ->get();

        $groupMessages = $squashedCommits->pluck('message')->push($commit->message);
        $groupBranch = collect([$commit->branch])->merge($squashedCommits->pluck('branch'))->filter()->implode(' ');

        $matcher = new CommitStageMatcherService;
        $suggestedStage = $matcher->match($groupMessages->implode(' '), $groupBranch)
            ?? $commit->aiSuggestedStage;
        $stages = ProjectStage::query()->orderBy('sort_order')->get();

        $defaultNotes = $commit->ai_summary ?: $groupMessages->implode('; ');

        return view('projects.pending-commits.convert', [
            'project' => $project,
            'commit' => $commit,
            'squashedCommits' => $squashedCommits,
            'suggestedStage' => $suggestedStage,
            'stages' => $stages,
            'defaultNotes' => $defaultNotes,
        ]);
    }

    public function squash(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'commit_uuids' => ['required', 'array'],
            'commit_uuids.*' => ['string'],
        ]);

        $commits = CommitTimeEntry::query()
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->whereIn('uuid', $validated['commit_uuids'])
            ->orderBy('committed_at')
            ->get();

        if ($commits->count() < 2) {
            throw ValidationException::withMessages([
                'commit_uuids' => __('Select at least two pending commits to squash.'),
            ])->redirectTo(route('projects.pending-commits.index', $project));
        }

        $anchor = $commits->shift();

        foreach ($commits as $child) {
            $child->update([
                'status' => 'squashed',
                'squashed_into' => $anchor->id,
            ]);
        }

        SummarizeSquashedCommits::dispatch($anchor);

        return redirect()
            ->route('projects.pending-commits.create', [$project, $anchor])
            ->with('status', 'commits-squashed');
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
        if (! empty($validated['project_stage_uuid'])) {
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

        CommitTimeEntry::query()
            ->where('squashed_into', $commit->id)
            ->update([
                'status' => 'approved',
                'converted_time_entry_id' => $timeEntry->id,
            ]);

        return redirect()
            ->route('projects.pending-commits.index', $project)
            ->with('status', 'commit-converted');
    }
}
