<?php

namespace Database\Seeders;

use App\Models\ProjectStage;
use Illuminate\Database\Seeder;

class ProjectStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            ['name' => 'Discovery', 'sort_order' => 1],
            ['name' => 'Analysis', 'sort_order' => 2],
            ['name' => 'Design', 'sort_order' => 3],
            ['name' => 'Development', 'sort_order' => 4],
            ['name' => 'Testing', 'sort_order' => 5],
            ['name' => 'Deployment', 'sort_order' => 6],
            ['name' => 'Maintenance', 'sort_order' => 7],
        ];

        foreach ($stages as $stage) {
            ProjectStage::query()->updateOrCreate(
                ['name' => $stage['name']],
                [
                    'sort_order' => $stage['sort_order'],
                    'status' => 'active',
                ]
            );
        }

        ProjectStage::query()->whereNotIn('name', array_column($stages, 'name'))->delete();
    }
}
