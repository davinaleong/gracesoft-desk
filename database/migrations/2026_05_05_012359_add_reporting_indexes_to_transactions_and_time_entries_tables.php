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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'direction', 'transaction_date'], 'trx_status_direction_date_idx');
            $table->index(['project_id', 'transaction_date'], 'trx_project_date_idx');
            $table->index(['transaction_category_id', 'status', 'transaction_date'], 'trx_category_status_date_idx');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->index(['entry_date', 'project_id'], 'time_entries_date_project_idx');
            $table->index(['entry_date', 'project_stage_id'], 'time_entries_date_stage_idx');
            $table->index(['is_billable', 'entry_date'], 'time_entries_billable_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('trx_status_direction_date_idx');
            $table->dropIndex('trx_project_date_idx');
            $table->dropIndex('trx_category_status_date_idx');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('time_entries_date_project_idx');
            $table->dropIndex('time_entries_date_stage_idx');
            $table->dropIndex('time_entries_billable_date_idx');
        });
    }
};
