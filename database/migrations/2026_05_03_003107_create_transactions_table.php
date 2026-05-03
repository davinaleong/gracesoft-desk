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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('transaction_code')->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->enum('direction', ['in', 'out']);
            $table->enum('status', ['pending', 'completed', 'void'])->default('completed');
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('gst_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->timestamps();

            $table->index('transaction_date');
            $table->index(['project_id', 'transaction_date']);
            $table->index(['transaction_category_id', 'transaction_date']);
            $table->index(['account_id', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
