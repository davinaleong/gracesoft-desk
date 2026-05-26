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
            ['name' => 'Discovery', 'slug' => 'discovery', 'sort_order' => 1],
            ['name' => 'Planning', 'slug' => 'planning', 'sort_order' => 2],
            ['name' => 'Design', 'slug' => 'design', 'sort_order' => 3],
            ['name' => 'Development', 'slug' => 'development', 'sort_order' => 4],
            ['name' => 'Testing', 'slug' => 'testing', 'sort_order' => 5],
            ['name' => 'Deployment', 'slug' => 'deployment', 'sort_order' => 6],
            ['name' => 'Maintenance', 'slug' => 'maintenance', 'sort_order' => 7],
        ];

        foreach ($stages as $stage) {
            ProjectStage::query()->updateOrCreate(
                ['slug' => $stage['slug']],
                [
                    'project_id' => null,
                    'name' => $stage['name'],
                    'sort_order' => $stage['sort_order'],
                    'status' => 'active',
                ]
            );
        }
    }
}
