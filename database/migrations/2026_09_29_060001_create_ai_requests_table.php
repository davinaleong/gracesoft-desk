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
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamp('requested_at')->index();
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->string('purpose', 50);
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->char('payload_hash', 64);
            $table->unsignedInteger('payload_length');
            $table->string('outcome', 20);
            $table->string('error', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('ai_opt_out')->default(false)->after('is_billable');
        });

        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->json('file_paths')->nullable()->after('changed_files');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commit_time_entries', function (Blueprint $table) {
            $table->dropColumn('file_paths');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ai_opt_out');
        });

        Schema::dropIfExists('ai_requests');
    }
};
