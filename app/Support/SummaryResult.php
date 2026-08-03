<?php

namespace App\Support;

readonly class SummaryResult
{
    public function __construct(
        public string $summary,
        public ?string $suggestedStageName,
    ) {}
}
