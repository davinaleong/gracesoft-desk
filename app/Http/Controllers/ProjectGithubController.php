<?php

namespace App\Http\Controllers;

use App\Models\GithubConnection;
use App\Models\Project;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectGithubController extends Controller
{
    /** JSON endpoint used by the repo picker to list accessible repositories. */
    public function repos(): JsonResponse
    {
        $connection = Auth::user()->githubConnection;

        if (! $connection) {
            return response()->json(['error' => 'No GitHub connection found.'], 403);
        }

        $service = new GitHubService($connection->access_token);
        $repos = $service->listRepositories();

        return response()->json($repos);
    }

    /** Link a GitHub repository to a project and register a push webhook. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'github_repo' => [
                'required',
                'string',
                'regex:/^[^\/]+\/[^\/]+$/',
                Rule::unique('projects', 'github_repo')->ignore($project->id),
            ],
        ], [
            'github_repo.unique' => __('This repository is already linked to another project.'),
        ]);

        $connection = Auth::user()->githubConnection;

        abort_unless($connection instanceof GithubConnection, 403, 'Connect GitHub first.');

        // Unlink any previous repo/webhook before linking a new one.
        $this->unlinkWebhook($connection, $project);

        $secret = Str::random(40);
        $webhookUrl = route('webhooks.github', $project);

        $service = new GitHubService($connection->access_token);
        $webhookId = $service->registerWebhook($validated['github_repo'], $webhookUrl, $secret);

        $project->update([
            'github_repo' => $validated['github_repo'],
            'github_webhook_id' => $webhookId,
            'github_webhook_secret' => $secret,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'github-repo-linked');
    }

    /** Unlink the GitHub repository from a project and remove the webhook. */
    public function destroy(Project $project): RedirectResponse
    {
        $connection = Auth::user()->githubConnection;

        if ($connection instanceof GithubConnection) {
            $this->unlinkWebhook($connection, $project);
        }

        $project->update([
            'github_repo' => null,
            'github_webhook_id' => null,
            'github_webhook_secret' => null,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'github-repo-unlinked');
    }

    private function unlinkWebhook(GithubConnection $connection, Project $project): void
    {
        if ($project->github_repo && $project->github_webhook_id) {
            $service = new GitHubService($connection->access_token);
            $service->removeWebhook($project->github_repo, $project->github_webhook_id);
        }
    }
}
