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
            $table->string('billing_model', 20)->default('hourly')->after('hourly_rate');
            $table->decimal('fixed_fee_total', 12, 2)->nullable()->after('billing_model');
            $table->decimal('retainer_monthly_amount', 12, 2)->nullable()->after('fixed_fee_total');
            $table->decimal('retainer_included_hours', 8, 2)->nullable()->after('retainer_monthly_amount');
            $table->decimal('retainer_overage_rate', 12, 2)->nullable()->after('retainer_included_hours');
            $table->boolean('retainer_rollover')->default(false)->after('retainer_overage_rate');
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('retainer_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('included_hours', 8, 2);
            $table->decimal('rollover_in_hours', 8, 2)->default(0);
            $table->decimal('hours_used', 8, 2);
            $table->decimal('overage_hours', 8, 2)->default(0);
            $table->decimal('rollover_out_hours', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retainer_periods');
        Schema::dropIfExists('milestones');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'billing_model',
                'fixed_fee_total',
                'retainer_monthly_amount',
                'retainer_included_hours',
                'retainer_overage_rate',
                'retainer_rollover',
            ]);
        });
    }
};
