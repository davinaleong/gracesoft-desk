<?php

namespace App\Services;

use App\Models\ProjectStage;

class CommitStageMatcherService
{
    /**
     * Match a commit message + branch against SDLC stage keywords.
     * Returns the first stage whose keywords appear in the text, ordered by sort_order.
     */
    public function match(string $message, string $branch = ''): ?ProjectStage
    {
        $haystack = strtolower($message.' '.$branch);

        $stages = ProjectStage::query()
            ->orderBy('sort_order')
            ->whereNotNull('keywords')
            ->get();

        foreach ($stages as $stage) {
            foreach ((array) ($stage->keywords ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($haystack, strtolower($keyword))) {
                    return $stage;
                }
            }
        }

        return null;
    }

    /** Snap a duration to the nearest 15-minute increment (minimum 15). */
    public static function snapDuration(int $minutes): int
    {
        return max(15, (int) round($minutes / 15) * 15);
    }
}
