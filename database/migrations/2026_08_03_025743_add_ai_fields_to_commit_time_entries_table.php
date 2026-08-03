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
            $table->text('ai_summary')->nullable()->after('message');
            $table->foreignId('ai_suggested_stage_id')->nullable()->constrained('project_stages')->nullOnDelete()->after('ai_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_suggested_stage_id');
            $table->dropColumn('ai_summary');
        });
    }
};
