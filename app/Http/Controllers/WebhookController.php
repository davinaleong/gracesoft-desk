<?php

namespace App\Http\Controllers;

use App\Models\CommitTimeEntry;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        // Determine if commit stats need to be fetched separately
        $firstCommit = $commits[0] ?? [];
        $statsInPayload = isset($firstCommit['added'], $firstCommit['removed'], $firstCommit['modified']);

        foreach ($commits as $commit) {
            $sha = $commit['id'] ?? null;

            if (! $sha) {
                continue;
            }

            $additions = null;
            $deletions = null;
            $changedFiles = null;

            if ($statsInPayload) {
                $changedFiles = count($commit['added'] ?? [])
                    + count($commit['removed'] ?? [])
                    + count($commit['modified'] ?? []);
            }

            CommitTimeEntry::updateOrCreate(
                ['project_id' => $project->id, 'sha' => $sha],
                [
                    'branch' => $branch,
                    'author_name' => $commit['author']['name'] ?? null,
                    'author_email' => $commit['author']['email'] ?? null,
                    'committed_at' => isset($commit['timestamp'])
                        ? Carbon::parse($commit['timestamp'])
                        : null,
                    'message' => $commit['message'] ?? '',
                    'additions' => $additions,
                    'deletions' => $deletions,
                    'changed_files' => $changedFiles,
                    'status' => 'pending',
                ]
            );
        }
    }
}
