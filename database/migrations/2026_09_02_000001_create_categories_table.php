<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['vendor', 'service']);
            $table->string('name');
            $table->string('code')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index(['type', 'status']);
        });

        $now = now();

        $vendorCategories = ['telco', 'cloud', 'saas', 'professional_services', 'utilities', 'other'];
        $serviceCategories = ['storage', 'communication', 'design', 'dev_tools', 'security', 'productivity', 'other'];

        $rows = [];

        foreach ($vendorCategories as $code) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'type' => 'vendor',
                'name' => str_replace('_', ' ', ucfirst($code)),
                'code' => $code,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($serviceCategories as $code) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'type' => 'service',
                'name' => str_replace('_', ' ', ucfirst($code)),
                'code' => $code,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('categories')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
