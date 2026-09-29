<?php

namespace App\Support;

/**
 * A push, normalised across providers.
 *
 * Each commit has exactly: sha, author_name, author_email, timestamp (ISO 8601 with offset),
 * message, branch, files_changed (int|null) and file_paths (array<int, string>|null).
 */
readonly class PushEvent
{
    /**
     * @param  array<int, array{sha: string, author_name: ?string, author_email: ?string, timestamp: ?string, message: string, branch: string, files_changed: ?int, file_paths: ?array<int, string>}>  $commits
     */
    public function __construct(
        public string $branch,
        public array $commits,
    ) {}

    /**
     * @param  array<string, mixed>  $commit
     * @return array{sha: string, author_name: ?string, author_email: ?string, timestamp: ?string, message: string, branch: string, files_changed: ?int, file_paths: ?array<int, string>}
     */
    public static function commit(array $commit): array
    {
        return [
            'sha' => (string) $commit['sha'],
            'author_name' => $commit['author_name'] ?? null,
            'author_email' => $commit['author_email'] ?? null,
            'timestamp' => $commit['timestamp'] ?? null,
            'message' => (string) ($commit['message'] ?? ''),
            'branch' => (string) ($commit['branch'] ?? ''),
            'files_changed' => $commit['files_changed'] ?? null,
            'file_paths' => $commit['file_paths'] ?? null,
        ];
    }
}
