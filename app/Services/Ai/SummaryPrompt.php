<?php

namespace App\Services\Ai;

use App\Support\SummaryResult;

/**
 * The provider-neutral prompt and response parsing shared by every driver.
 */
class SummaryPrompt
{
    /**
     * @param  array{commits: array<int, array<string, mixed>>, stages: array<int, array{name: string, keywords: array<int, string>}>}  $payload
     */
    public static function build(array $payload): string
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You write time-entry notes for a software consultancy's timesheet.

Below is JSON describing one or more git commits (message, branch, number of changed files, and sometimes file paths) and the project's SDLC stages with their keywords.
Write a concise 1–2 sentence plain-English summary of the work, suitable as a time-entry note, and pick the single best-fitting stage name from the list.
Some values are masked as [redacted]; ignore them.

{$data}

Respond with JSON only, exactly in this shape: {"summary":"…","stage":"…"}
PROMPT;
    }

    public static function parse(string $text): SummaryResult
    {
        $text = trim($text);

        if (preg_match('/\{.*\}/s', $text, $match) === 1) {
            $text = $match[0];
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            return new SummaryResult(summary: '', suggestedStageName: null);
        }

        return new SummaryResult(
            summary: trim((string) ($data['summary'] ?? '')),
            suggestedStageName: isset($data['stage']) ? trim((string) $data['stage']) : null,
        );
    }
}
