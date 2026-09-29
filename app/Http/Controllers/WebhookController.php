<?php

namespace App\Http\Controllers;

use App\Jobs\IngestLargePushBatch;
use App\Models\Project;
use App\Services\CommitIngestionService;
use App\Services\PushSizeThresholdService;
use App\Services\SourceProviders\SourceProviderRegistry;
use App\Support\PushEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(private SourceProviderRegistry $providers) {}

    /** GitHub keeps its original route name; it is the generic receiver with provider = github. */
    public function github(Request $request, Project $project): Response
    {
        return $this->receive($request, 'github', $project);
    }

    /**
     * Push webhook for any provider, verified with that provider's own signature or secret-token check.
     */
    public function receive(Request $request, string $provider, Project $project): Response
    {
        $source = $this->providers->get($provider);

        // A project only accepts pushes from the provider it is linked to.
        abort_unless($project->sourceProviderKey() === $source->key(), 404);

        $secret = (string) $project->github_webhook_secret;

        if ($secret === '' || ! $source->verifyRequest($request, $secret)) {
            abort(403, 'Invalid or missing webhook signature.');
        }

        // Acknowledge non-push events immediately
        if (! $source->isPushEvent($request)) {
            return response()->noContent();
        }

        $this->ingestPush($project, $source->parsePush($request->json()->all()));

        return response()->noContent();
    }

    private function ingestPush(Project $project, PushEvent $push): void
    {
        if ($push->commits === []) {
            return;
        }

        // Projects linked before branch tracking have no github_branch set
        // and keep ingesting every branch; once a branch is chosen, pushes
        // to any other branch are ignored.
        if ($project->github_branch && $project->github_branch !== $push->branch) {
            return;
        }

        $pushBatchUuid = (string) Str::uuid();

        $isLargePush = app(PushSizeThresholdService::class)->isLargePush($project, count($push->commits));

        // Large pushes are handed off to a queued job rather than processed
        // inline — upserting hundreds of commits synchronously risks the
        // webhook request timing out on the provider's side.
        if ($isLargePush) {
            IngestLargePushBatch::dispatch($project, $push->branch, $push->commits, $pushBatchUuid);

            return;
        }

        app(CommitIngestionService::class)->ingest($project, $push->branch, $push->commits, $pushBatchUuid, false);
    }
}
