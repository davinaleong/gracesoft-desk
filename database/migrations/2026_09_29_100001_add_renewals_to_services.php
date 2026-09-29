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
        Schema::table('services', function (Blueprint $table) {
            $table->string('billing_cycle', 10)->nullable()->after('plan');
            $table->decimal('expected_amount', 12, 2)->nullable()->after('billing_cycle');
            $table->char('currency', 3)->default('SGD')->after('expected_amount');
            $table->date('next_renewal_date')->nullable()->after('currency');
            $table->unsignedTinyInteger('renewal_anchor_day')->nullable()->after('next_renewal_date');
            $table->foreignId('account_id')->nullable()->after('renewal_anchor_day')->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
            $table->foreignId('transaction_category_id')->nullable()->after('payment_method_id')->constrained()->nullOnDelete();
            $table->boolean('auto_create_expense')->default(false)->after('transaction_category_id');
            $table->unsignedSmallInteger('reminder_days_before')->default(7)->after('auto_create_expense');
            $table->date('last_reminded_for')->nullable()->after('reminder_days_before');

            $table->index('next_renewal_date');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            $table->date('service_renewal_date')->nullable()->after('service_id');

            $table->unique(['service_id', 'service_renewal_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL uses the unique index for the service_id foreign key, so the key goes first.
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['service_id', 'service_renewal_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['service_id', 'service_renewal_date']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['next_renewal_date']);
            $table->dropConstrainedForeignId('account_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropConstrainedForeignId('transaction_category_id');
            $table->dropColumn([
                'billing_cycle',
                'expected_amount',
                'currency',
                'next_renewal_date',
                'renewal_anchor_day',
                'auto_create_expense',
                'reminder_days_before',
                'last_reminded_for',
            ]);
        });
    }
};
