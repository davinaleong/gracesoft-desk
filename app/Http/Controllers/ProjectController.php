<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $projects = Project::query()
            ->with('client')
            ->latest()
            ->paginate(15);

        return view('projects.index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('projects.create', [
            'clients' => $this->clientOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create($this->resolveClient($request->validated()));

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'project-created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): View
    {
        $project->load(['documents', 'client']);

        $unlinkedDocuments = Document::query()->whereNull('documentable_id')->orderBy('name')->get();

        return view('projects.show', [
            'project' => $project,
            'unlinkedDocuments' => $unlinkedDocuments,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project): View
    {
        $project->load('client');

        return view('projects.edit', [
            'project' => $project,
            'clients' => $this->clientOptions($project->client_id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($this->resolveClient($request->validated()));

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'project-updated');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveClient(array $payload): array
    {
        $payload['client_id'] = filled($payload['client_uuid'] ?? null)
            ? Client::query()->where('uuid', (string) $payload['client_uuid'])->value('id')
            : null;

        unset($payload['client_uuid']);

        return $payload;
    }

    /**
     * @return Collection<int, Client>
     */
    private function clientOptions(?int $includeClientId = null): Collection
    {
        return Client::query()
            ->where(fn ($q) => $q->active()->when($includeClientId, fn ($q) => $q->orWhere('id', $includeClientId)))
            ->orderBy('name')
            ->get();
    }
}
