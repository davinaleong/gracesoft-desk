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
    /** JSON endpoint used by the repo picker to list a GitHub account's accessible repositories. */
    public function repos(int $connection): JsonResponse
    {
        $connection = Auth::user()->githubConnections()->findOrFail($connection);

        return response()->json((new GitHubService($connection->access_token))->listRepositories());
    }

    /** JSON endpoint used by the branch picker to list a repository's branches. */
    public function branches(Request $request, int $connection): JsonResponse
    {
        $connection = Auth::user()->githubConnections()->findOrFail($connection);

        $validated = $request->validate([
            'repo' => ['required', 'string', 'regex:/^[^\/]+\/[^\/]+$/'],
        ]);

        return response()->json((new GitHubService($connection->access_token))->listBranches($validated['repo']));
    }

    /** Link a GitHub repository + branch to a project and register a push webhook. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'github_connection_id' => [
                'required',
                'integer',
                Rule::exists('github_connections', 'id')->where('user_id', Auth::id()),
            ],
            'github_repo' => [
                'required',
                'string',
                'regex:/^[^\/]+\/[^\/]+$/',
                Rule::unique('projects', 'github_repo')->ignore($project->id),
            ],
            'github_branch' => ['required', 'string'],
        ], [
            'github_repo.unique' => __('This repository is already linked to another project.'),
        ]);

        $connection = Auth::user()->githubConnections()->findOrFail($validated['github_connection_id']);

        // Unlink any previous repo/webhook (using the account that created it) before linking a new one.
        $this->unlinkWebhook($project);

        $secret = Str::random(40);
        $webhookUrl = route('webhooks.github', $project);

        $service = new GitHubService($connection->access_token);
        $webhookId = $service->registerWebhook($validated['github_repo'], $webhookUrl, $secret);

        $project->update([
            'github_connection_id' => $connection->id,
            'github_repo' => $validated['github_repo'],
            'github_branch' => $validated['github_branch'],
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
        $this->unlinkWebhook($project);

        $project->update([
            'github_connection_id' => null,
            'github_repo' => null,
            'github_branch' => null,
            'github_webhook_id' => null,
            'github_webhook_secret' => null,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'github-repo-unlinked');
    }

    /** Remove the project's webhook using the GitHub account that registered it, if that account is still connected. */
    private function unlinkWebhook(Project $project): void
    {
        $connection = $project->githubConnection;

        if ($connection instanceof GithubConnection && $project->github_repo && $project->github_webhook_id) {
            (new GitHubService($connection->access_token))
                ->removeWebhook($project->github_repo, $project->github_webhook_id);
        }
    }
}
