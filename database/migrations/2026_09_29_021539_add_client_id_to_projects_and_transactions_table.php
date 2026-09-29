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
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->decimal('hourly_rate', 12, 2)->nullable()->default(null)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        DB::table('projects')->whereNull('hourly_rate')->update(['hourly_rate' => 0]);

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('hourly_rate', 12, 2)->default(0)->change();
        });
    }
};
