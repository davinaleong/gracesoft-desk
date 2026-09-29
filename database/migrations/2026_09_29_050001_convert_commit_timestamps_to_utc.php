<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * committed_at used to hold the pusher's wall-clock time with the offset dropped.
 * Assume that wall clock was the system timezone and rewrite the values as UTC.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->shift(fn (CarbonImmutable $moment, string $timezone): CarbonImmutable => CarbonImmutable::parse($moment->format('Y-m-d H:i:s'), $timezone)->utc());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->shift(fn (CarbonImmutable $moment, string $timezone): CarbonImmutable => CarbonImmutable::parse($moment->format('Y-m-d H:i:s'), 'UTC')->setTimezone($timezone));
    }

    /**
     * @param  callable(CarbonImmutable, string): CarbonImmutable  $convert
     */
    private function shift(callable $convert): void
    {
        $timezone = (string) (DB::table('system_settings')->where('key', 'timezone')->value('value') ?: 'UTC');

        if (! in_array($timezone, timezone_identifiers_list(), true) || $timezone === 'UTC') {
            return;
        }

        DB::table('commit_time_entries')
            ->whereNotNull('committed_at')
            ->orderBy('id')
            ->select(['id', 'committed_at'])
            ->chunkById(500, function ($rows) use ($convert, $timezone): void {
                foreach ($rows as $row) {
                    DB::table('commit_time_entries')->where('id', $row->id)->update([
                        'committed_at' => $convert(CarbonImmutable::parse($row->committed_at, 'UTC'), $timezone)->format('Y-m-d H:i:s'),
                    ]);
                }
            });
    }
};
