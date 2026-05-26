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
        $canonicalStages = [
            'Discovery' => 1,
            'Analysis' => 2,
            'Design' => 3,
            'Development' => 4,
            'Testing' => 5,
            'Deployment' => 6,
            'Maintenance' => 7,
        ];

        $normalizedStageMap = [
            'discovery' => 'Discovery',
            'planning' => 'Analysis',
            'analysis' => 'Analysis',
            'design' => 'Design',
            'development' => 'Development',
            'execution' => 'Development',
            'implementation' => 'Development',
            'testing' => 'Testing',
            'deployment' => 'Deployment',
            'maintenance' => 'Maintenance',
        ];

        DB::transaction(function () use ($canonicalStages, $normalizedStageMap): void {
            $now = now();
            $canonicalIds = [];

            foreach ($canonicalStages as $name => $sortOrder) {
                $existing = DB::table('project_stages')
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->orderBy('id')
                    ->first(['id']);

                if ($existing) {
                    DB::table('project_stages')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $name,
                            'sort_order' => $sortOrder,
                            'status' => 'active',
                            'updated_at' => $now,
                        ]);

                    $canonicalIds[$name] = (int) $existing->id;

                    continue;
                }

                $canonicalIds[$name] = (int) DB::table('project_stages')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'project_id' => null,
                    'name' => $name,
                    'slug' => strtolower($name),
                    'sort_order' => $sortOrder,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $allStages = DB::table('project_stages')->get(['id', 'name']);

            foreach ($allStages as $stage) {
                $normalized = strtolower(trim((string) $stage->name));
                $targetStage = $normalizedStageMap[$normalized] ?? 'Analysis';
                $targetId = $canonicalIds[$targetStage] ?? null;

                if (! $targetId || (int) $stage->id === $targetId) {
                    continue;
                }

                DB::table('time_entries')
                    ->where('project_stage_id', (int) $stage->id)
                    ->update(['project_stage_id' => $targetId]);
            }

            DB::table('time_entries')
                ->whereNull('project_stage_id')
                ->update(['project_stage_id' => $canonicalIds['Analysis']]);

            DB::table('project_stages')
                ->whereNotIn('id', array_values($canonicalIds))
                ->delete();

            foreach ($canonicalStages as $name => $sortOrder) {
                DB::table('project_stages')
                    ->where('id', $canonicalIds[$name])
                    ->update([
                        'name' => $name,
                        'sort_order' => $sortOrder,
                        'status' => 'active',
                        'updated_at' => $now,
                    ]);
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('project_stages', function (Blueprint $table): void {
                $table->dropForeign(['project_id']);
            });

            Schema::table('project_stages', function (Blueprint $table): void {
                $table->dropIndex(['project_id']);
                $table->dropUnique(['slug']);
                $table->dropColumn(['project_id', 'slug']);
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('project_stages_tmp', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        DB::table('project_stages_tmp')->insertUsing(
            ['id', 'uuid', 'name', 'sort_order', 'status', 'created_at', 'updated_at'],
            DB::table('project_stages')->select(['id', 'uuid', 'name', 'sort_order', 'status', 'created_at', 'updated_at'])
        );

        Schema::drop('project_stages');
        Schema::rename('project_stages_tmp', 'project_stages');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stages', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->index()->after('uuid');
            $table->string('slug')->nullable()->after('name');
        });
    }
};
