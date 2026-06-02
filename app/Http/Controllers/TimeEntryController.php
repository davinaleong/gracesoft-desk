<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TimeEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $timeEntries = TimeEntry::query()
            ->with(['project', 'stage'])
            ->latest('entry_date')
            ->paginate(20);

        return view('time-entries.index', [
            'timeEntries' => $timeEntries,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('time-entries.create', [
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => ProjectStage::query()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTimeEntryRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload = $this->resolveForeignKeys($payload);
        $payload['user_id'] = $request->user()?->id;
        $payload['billable_amount'] = $this->calculateBillableAmount($payload);

        $timeEntry = TimeEntry::query()->create($payload);

        return redirect()
            ->route('time-entries.show', $timeEntry)
            ->with('status', 'time-entry-created');
    }

    /**
     * Display the specified resource.
     */
    public function show(TimeEntry $timeEntry): View
    {
        $timeEntry->load(['project', 'stage', 'documents']);

        return view('time-entries.show', [
            'timeEntry' => $timeEntry,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TimeEntry $timeEntry): View
    {
        $timeEntry->load(['project', 'stage']);

        return view('time-entries.edit', [
            'timeEntry' => $timeEntry,
            'projects' => Project::query()->orderBy('name')->get(),
            'stages' => ProjectStage::query()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        $payload = $request->validated();
        $payload = $this->resolveForeignKeys($payload);
        $payload['billable_amount'] = $this->calculateBillableAmount($payload);

        $timeEntry->update($payload);

        return redirect()
            ->route('time-entries.index')
            ->with('status', 'time-entry-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $timeEntry->delete();

        return redirect()
            ->route('time-entries.index')
            ->with('status', 'time-entry-deleted');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calculateBillableAmount(array $payload): float
    {
        if (! ((bool) $payload['is_billable'])) {
            return 0;
        }

        $minutes = (int) $payload['duration_minutes'];
        $hourlyRate = (float) ($payload['hourly_rate'] ?? 0);

        if ($minutes <= 0 || $hourlyRate <= 0) {
            return 0;
        }

        return round(($minutes / 60) * $hourlyRate, 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveForeignKeys(array $payload): array
    {
        $payload['project_id'] = Project::query()->where('uuid', (string) $payload['project_uuid'])->value('id');
        $payload['project_stage_id'] = isset($payload['project_stage_uuid'])
            ? ProjectStage::query()->where('uuid', (string) $payload['project_stage_uuid'])->value('id')
            : null;

        unset($payload['project_uuid'], $payload['project_stage_uuid']);

        return $payload;
    }
}
