<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->uuid('push_batch_uuid')->nullable()->after('sha')->index();
            $table->boolean('from_large_batch')->default(false)->after('push_batch_uuid')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->dropColumn(['push_batch_uuid', 'from_large_batch']);
        });
    }
};
