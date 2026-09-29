<?php

namespace App\Http\Controllers;

use App\Models\GithubConnection;
use App\Models\Project;
use App\Services\SourceProviders\SourceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Links a project to a repository on any source provider. The routes keep their original "github" names;
 * the connection's provider decides which Git host is called.
 */
class ProjectGithubController extends Controller
{
    public function __construct(private SourceProviderRegistry $providers) {}

    /** JSON endpoint used by the repo picker to list an account's accessible repositories. */
    public function repos(int $connection): JsonResponse
    {
        $connection = Auth::user()->githubConnections()->findOrFail($connection);

        return response()->json($this->providers->get($connection->provider)->listRepositories($connection));
    }

    /** JSON endpoint used by the branch picker to list a repository's branches. */
    public function branches(Request $request, int $connection): JsonResponse
    {
        $connection = Auth::user()->githubConnections()->findOrFail($connection);
        $provider = $this->providers->get($connection->provider);

        $validated = $request->validate([
            'repo' => ['required', 'string', 'regex:'.$provider->repoPattern()],
        ]);

        return response()->json($provider->listBranches($connection, $validated['repo']));
    }

    /** Link a repository + branch to a project and register a push webhook. */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $request->validate([
            'github_connection_id' => [
                'required',
                'integer',
                Rule::exists('github_connections', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $connection = Auth::user()->githubConnections()->findOrFail($request->integer('github_connection_id'));
        $provider = $this->providers->get($connection->provider);

        $validated = $request->validate([
            'github_repo' => [
                'required',
                'string',
                'regex:'.$provider->repoPattern(),
                Rule::unique('projects', 'github_repo')
                    ->where(fn ($query) => $provider->key() === 'github'
                        ? $query->where(fn ($q) => $q->where('source_provider', 'github')->orWhereNull('source_provider'))
                        : $query->where('source_provider', $provider->key()))
                    ->ignore($project->id),
            ],
            'github_branch' => ['required', 'string'],
        ], [
            'github_repo.unique' => __('This repository is already linked to another project.'),
        ]);

        // Unlink any previous repo/webhook (using the account that created it) before linking a new one.
        $this->unlinkWebhook($project);

        $secret = Str::random(40);
        $webhookUrl = $provider->key() === 'github'
            ? route('webhooks.github', $project)
            : route('webhooks.receive', [$provider->key(), $project]);

        $webhookId = $provider->registerWebhook($connection, $validated['github_repo'], $webhookUrl, $secret);

        $project->update([
            'github_connection_id' => $connection->id,
            'source_provider' => $provider->key(),
            'github_repo' => $validated['github_repo'],
            'github_branch' => $validated['github_branch'],
            'github_webhook_id' => ctype_digit($webhookId) ? (int) $webhookId : null,
            'source_webhook_ref' => $webhookId,
            'github_webhook_secret' => $secret,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'github-repo-linked');
    }

    /** Unlink the repository from a project and remove the webhook. */
    public function destroy(Project $project): RedirectResponse
    {
        $this->unlinkWebhook($project);

        $project->update([
            'github_connection_id' => null,
            'source_provider' => null,
            'github_repo' => null,
            'github_branch' => null,
            'github_webhook_id' => null,
            'source_webhook_ref' => null,
            'github_webhook_secret' => null,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'github-repo-unlinked');
    }

    /** Remove the project's webhook using the account that registered it, if that account is still connected. */
    private function unlinkWebhook(Project $project): void
    {
        $connection = $project->githubConnection;
        $webhookId = $project->source_webhook_ref ?: ($project->github_webhook_id ? (string) $project->github_webhook_id : null);

        if ($connection instanceof GithubConnection && $project->github_repo && $webhookId) {
            $this->providers->get($project->sourceProviderKey())
                ->removeWebhook($connection, $project->github_repo, $webhookId);
        }
    }
}
