<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectMapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mapFilePath = base_path('_internal-docs/data/project-map.md');

        if (! is_file($mapFilePath)) {
            return;
        }

        $content = file_get_contents($mapFilePath);

        if (! is_string($content) || $content === '') {
            return;
        }

        preg_match_all('/^\*\s*(.+?)\s*>\s*([A-Z0-9\-]+)\s*$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $projectName = trim((string) ($match[1] ?? ''));
            $projectCode = trim((string) ($match[2] ?? ''));

            if ($projectName === '' || $projectCode === '') {
                continue;
            }

            Project::query()->updateOrCreate(
                ['code' => $projectCode],
                [
                    'name' => $projectName,
                    'status' => 'active',
                    'is_billable' => true,
                ]
            );
        }
    }
}
