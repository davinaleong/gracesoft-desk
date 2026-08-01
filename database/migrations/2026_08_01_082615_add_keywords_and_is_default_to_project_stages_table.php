<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_stages', function (Blueprint $table): void {
            $table->json('keywords')->nullable()->after('status');
            $table->boolean('is_default')->default(false)->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('project_stages', function (Blueprint $table): void {
            $table->dropColumn(['keywords', 'is_default']);
        });
    }
};
