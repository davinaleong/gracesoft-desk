<?php

namespace App\Http\Controllers;

use App\Jobs\IngestLargePushBatch;
use App\Models\Project;
use App\Services\CommitIngestionService;
use App\Services\PushSizeThresholdService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function github(Request $request, Project $project): Response
    {
        // HMAC signature verification
        $secret = $project->github_webhook_secret;
        $signature = $request->header('X-Hub-Signature-256');

        if (! $secret || ! $signature) {
            abort(403, 'Missing webhook secret or signature.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid webhook signature.');
        }

        $event = $request->header('X-GitHub-Event', '');

        // Acknowledge non-push events immediately
        if ($event !== 'push') {
            return response()->noContent();
        }

        $payload = $request->json()->all();
        $this->ingestPush($project, $payload);

        return response()->noContent();
    }

    /** @param array<string, mixed> $payload */
    private function ingestPush(Project $project, array $payload): void
    {
        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);
        $commits = $payload['commits'] ?? [];

        if (empty($commits)) {
            return;
        }

        // Projects linked before branch tracking have no github_branch set
        // and keep ingesting every branch; once a branch is chosen, pushes
        // to any other branch are ignored.
        if ($project->github_branch && $project->github_branch !== $branch) {
            return;
        }

        $pushBatchUuid = (string) Str::uuid();

        $isLargePush = app(PushSizeThresholdService::class)->isLargePush($project, count($commits));

        // Large pushes are handed off to a queued job rather than processed
        // inline — upserting hundreds of commits synchronously risks the
        // webhook request timing out on GitHub's side.
        if ($isLargePush) {
            IngestLargePushBatch::dispatch($project, $branch, $commits, $pushBatchUuid);

            return;
        }

        app(CommitIngestionService::class)->ingest($project, $branch, $commits, $pushBatchUuid, false);
    }
}
