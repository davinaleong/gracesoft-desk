<?php

namespace App\Contracts;

use App\Support\SummaryResult;

interface CommitSummarizer
{
    /**
     * Summarise commits and suggest an SDLC stage.
     *
     * The payload is already allow-listed and redacted by AiPayloadBuilder; drivers must send nothing else.
     *
     * @param  array{commits: array<int, array<string, mixed>>, stages: array<int, array{name: string, keywords: array<int, string>}>}  $payload
     */
    public function summarize(array $payload): SummaryResult;

    public function provider(): string;

    public function model(): string;
}
