<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_connections', function (Blueprint $table) {
            // The user_id FK was using the unique index; keep a plain index in its place.
            $table->index('user_id');
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'github_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('github_connection_id')
                ->nullable()
                ->after('github_branch')
                ->constrained('github_connections')
                ->nullOnDelete();
        });

        // Projects linked before multi-account support belong to the only connection that existed.
        DB::table('github_connections')->orderBy('id')->get()->each(function ($connection) {
            DB::table('projects')
                ->whereNotNull('github_repo')
                ->whereNull('github_connection_id')
                ->update(['github_connection_id' => $connection->id]);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('github_connection_id');
        });

        Schema::table('github_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'github_id']);
            $table->unique('user_id');
            $table->dropIndex(['user_id']);
        });
    }
};
