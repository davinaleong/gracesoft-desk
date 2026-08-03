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
        Schema::create('commit_time_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('sha', 40)->index();
            $table->string('branch')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->text('message');
            $table->integer('additions')->nullable();
            $table->integer('deletions')->nullable();
            $table->integer('changed_files')->nullable();
            $table->string('status')->default('pending')->index(); // pending | approved | squashed | ignored
            $table->foreignId('squashed_into')->nullable()->constrained('commit_time_entries')->nullOnDelete();
            $table->foreignId('converted_time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'sha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commit_time_entries');
    }
};
