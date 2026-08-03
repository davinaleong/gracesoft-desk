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
            $table->string('github_repo')->nullable()->after('hourly_rate');
            $table->unsignedBigInteger('github_webhook_id')->nullable()->after('github_repo');
            $table->text('github_webhook_secret')->nullable()->after('github_webhook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['github_repo', 'github_webhook_id', 'github_webhook_secret']);
        });
    }
};
