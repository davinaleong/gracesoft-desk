<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('type', 50);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('csv_s3_path')->nullable();
            $table->json('valid_rows');
            $table->json('invalid_rows')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
