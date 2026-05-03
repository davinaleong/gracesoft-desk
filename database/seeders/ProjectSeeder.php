<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Project::query()->updateOrCreate(
            ['code' => 'GS-OPS-001'],
            [
                'name' => 'GraceSoft Internal Ops Migration',
                'status' => 'active',
                'description' => 'Starter operational project used to bootstrap reporting and workflows.',
                'is_billable' => false,
            ]
        );
    }
}
