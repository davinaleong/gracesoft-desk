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
            $table->string('budget_type', 10)->default('none')->after('hourly_rate');
            $table->decimal('budget_value', 12, 2)->nullable()->after('budget_type');
            $table->json('budget_thresholds')->nullable()->after('budget_value');
            $table->unsignedInteger('budget_revision')->default(0)->after('budget_thresholds');
        });

        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('budget_revision');
            $table->unsignedSmallInteger('threshold');
            $table->string('budget_type', 10);
            $table->decimal('budget_value', 12, 2);
            $table->decimal('used_value', 12, 2);
            $table->timestamp('triggered_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'budget_revision', 'threshold']);
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget_type', 'budget_value', 'budget_thresholds', 'budget_revision']);
        });
    }
};
