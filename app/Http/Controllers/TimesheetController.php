<?php

namespace App\Http\Controllers;

use App\Jobs\SummarizeSquashedCommits;
use App\Models\CommitTimeEntry;
use App\Models\ProjectStage;
use App\Services\WeeklyTimesheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TimesheetController extends Controller
{
    public function __construct(private WeeklyTimesheetService $timesheet) {}

    public function index(Request $request): View
    {
        $weekStart = $this->timesheet->weekStart($request->string('week')->toString());

        return view('timesheet.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->addDays(6),
            'days' => $this->timesheet->groupByDayAndProject($this->timesheet->pendingForWeek($weekStart)),
            'dismissed' => $this->timesheet->dismissedForWeek($weekStart),
            'stages' => ProjectStage::query()->orderBy('sort_order')->get(),
            'loggedMinutes' => $this->timesheet->loggedMinutes($weekStart),
            'targetHours' => $this->timesheet->weeklyTargetHours(),
        ]);
    }

    public function convert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['uuid'],
            'group' => ['nullable', 'boolean'],
            'is_billable' => ['nullable', 'boolean'],
            'rows' => ['array'],
            'rows.*.minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rows.*.stage_uuid' => ['nullable', 'uuid', 'exists:project_stages,uuid'],
            'rows.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rows = [];
        foreach (array_unique($validated['selected']) as $uuid) {
            $rows[$uuid] = $validated['rows'][$uuid] ?? [];
        }

        $created = $this->timesheet->convert(
            rows: $rows,
            groupByDayAndProject: (bool) ($validated['group'] ?? false),
            isBillable: (bool) ($validated['is_billable'] ?? true),
            userId: $request->user()?->id,
        );

        return $this->backToWeek($validated['week'] ?? null)
            ->with('status', trans_choice('{0} Nothing to convert — those commits were already handled.|{1} Created :count time entry.|[2,*] Created :count time entries.', $created));
    }

    public function squash(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date'],
            'selected' => ['required', 'array', 'min:2'],
            'selected.*' => ['uuid'],
        ]);

        $commits = CommitTimeEntry::query()
            ->pending()
            ->whereIn('uuid', $validated['selected'])
            ->orderBy('committed_at')
            ->orderBy('id')
            ->get();

        if ($commits->count() < 2 || $commits->pluck('project_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'selected' => __('Select at least two pending commits from the same project to squash.'),
            ]);
        }

        $anchor = $commits->shift();

        CommitTimeEntry::query()->whereIn('id', $commits->pluck('id'))->update([
            'status' => 'squashed',
            'squashed_into' => $anchor->id,
        ]);

        SummarizeSquashedCommits::dispatch($anchor);

        return $this->backToWeek($validated['week'] ?? null)->with('status', __('Squashed :count commits.', ['count' => $commits->count() + 1]));
    }

    public function dismiss(Request $request): RedirectResponse
    {
        return $this->changeStatus($request, CommitTimeEntry::STATUS_PENDING, CommitTimeEntry::STATUS_DISMISSED, __('Dismissed :count commit(s).'));
    }

    public function restore(Request $request): RedirectResponse
    {
        return $this->changeStatus($request, CommitTimeEntry::STATUS_DISMISSED, CommitTimeEntry::STATUS_PENDING, __('Restored :count commit(s).'));
    }

    private function changeStatus(Request $request, string $from, string $to, string $message): RedirectResponse
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['uuid'],
        ]);

        $count = CommitTimeEntry::query()
            ->where('status', $from)
            ->whereIn('uuid', $validated['selected'])
            ->update(['status' => $to]);

        return $this->backToWeek($validated['week'] ?? null)->with('status', str_replace(':count', (string) $count, $message));
    }

    private function backToWeek(?string $week): RedirectResponse
    {
        return redirect()->route('timesheet.index', array_filter(['week' => $week]));
    }
}
