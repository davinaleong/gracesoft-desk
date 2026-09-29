<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises the GitHub integration to any source provider without renaming the
 * existing github_* columns (the GitHub contract tests and live data depend on them).
 * - github_connections now holds a connection for any provider (`provider` column).
 * - projects.github_repo / github_branch / github_webhook_secret hold the linked repo for whichever
 *   provider `source_provider` names; string webhook ids (Bitbucket) live in `source_webhook_ref`.
 * - commit_time_entries are de-duplicated on provider + repo + SHA.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('github_connections', function (Blueprint $table) {
            $table->string('provider', 20)->default('github')->after('user_id');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
        });

        Schema::table('github_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'github_id']);
            $table->unique(['user_id', 'provider', 'github_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('source_provider', 20)->nullable()->after('github_connection_id');
            $table->string('source_webhook_ref')->nullable()->after('github_webhook_secret');
        });

        DB::table('projects')->whereNotNull('github_repo')->update(['source_provider' => 'github']);

        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->string('provider', 20)->default('github')->after('project_id');
            $table->string('repo')->nullable()->after('provider');
        });

        DB::table('projects')->whereNotNull('github_repo')->orderBy('id')->each(function ($project): void {
            DB::table('commit_time_entries')->where('project_id', $project->id)->update(['repo' => $project->github_repo]);
        });

        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->unique(['provider', 'repo', 'sha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->dropUnique(['provider', 'repo', 'sha']);
        });

        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->dropColumn(['provider', 'repo']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['source_provider', 'source_webhook_ref']);
        });

        DB::table('github_connections')->where('provider', '!=', 'github')->delete();

        Schema::table('github_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider', 'github_id']);
            $table->unique(['user_id', 'github_id']);
        });

        Schema::table('github_connections', function (Blueprint $table) {
            $table->dropColumn(['provider', 'refresh_token', 'token_expires_at']);
        });
    }
};
