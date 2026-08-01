<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectStageRequest;
use App\Http\Requests\UpdateProjectStageRequest;
use App\Models\ProjectStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectStageController extends Controller
{
    public function index(): View
    {
        $stages = ProjectStage::query()->orderBy('sort_order')->get();

        return view('settings.project-stages.index', ['stages' => $stages]);
    }

    public function create(): View
    {
        return view('settings.project-stages.create');
    }

    public function store(StoreProjectStageRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['sort_order'] = ProjectStage::query()->max('sort_order') + 1;
        $validated['keywords'] = $this->parseKeywords($validated['keywords'] ?? null);

        ProjectStage::query()->create($validated);

        return redirect()
            ->route('settings.project-stages.index')
            ->with('status', 'project-stage-created');
    }

    public function edit(ProjectStage $projectStage): View
    {
        return view('settings.project-stages.edit', ['stage' => $projectStage]);
    }

    public function update(UpdateProjectStageRequest $request, ProjectStage $projectStage): RedirectResponse
    {
        $validated = $request->validated();
        $validated['keywords'] = $this->parseKeywords($validated['keywords'] ?? null);

        $projectStage->update($validated);

        return redirect()
            ->route('settings.project-stages.index')
            ->with('status', 'project-stage-updated');
    }

    public function destroy(ProjectStage $projectStage): RedirectResponse
    {
        if ($projectStage->timeEntries()->exists()) {
            return redirect()
                ->route('settings.project-stages.index')
                ->with('status', 'project-stage-in-use');
        }

        $projectStage->delete();

        return redirect()
            ->route('settings.project-stages.index')
            ->with('status', 'project-stage-deleted');
    }

    public function moveUp(ProjectStage $projectStage): RedirectResponse
    {
        $predecessor = ProjectStage::query()
            ->where('sort_order', '<', $projectStage->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($predecessor) {
            [$projectStage->sort_order, $predecessor->sort_order] = [$predecessor->sort_order, $projectStage->sort_order];
            $projectStage->save();
            $predecessor->save();
        }

        return redirect()->route('settings.project-stages.index');
    }

    public function moveDown(ProjectStage $projectStage): RedirectResponse
    {
        $successor = ProjectStage::query()
            ->where('sort_order', '>', $projectStage->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($successor) {
            [$projectStage->sort_order, $successor->sort_order] = [$successor->sort_order, $projectStage->sort_order];
            $projectStage->save();
            $successor->save();
        }

        return redirect()->route('settings.project-stages.index');
    }

    /**
     * @return array<int, string>|null
     */
    private function parseKeywords(?string $raw): ?array
    {
        if (is_null($raw) || trim($raw) === '') {
            return null;
        }

        return array_values(array_filter(
            array_map(fn (string $k): string => strtolower(trim($k)), explode(',', $raw)),
            fn (string $k): bool => $k !== '',
        ));
    }
}
