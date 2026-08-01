<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 12, 2)->default(0)->after('is_billable');
        });

        // One-time pass: set all projects to $45/hr
        DB::table('projects')->update(['hourly_rate' => 45.00]);

        // One-time pass: recalculate billable amounts at $45/hr
        DB::table('time_entries')
            ->where('is_billable', true)
            ->where('duration_minutes', '>', 0)
            ->update(['billable_amount' => DB::raw('ROUND(duration_minutes / 60.0 * 45.00, 2)')]);

        DB::table('time_entries')
            ->where('is_billable', false)
            ->update(['billable_amount' => 0]);

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('is_billable');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('hourly_rate');
        });
    }
};
