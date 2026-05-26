<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::query()->first() ?? Project::query()->create([
            'code' => 'TME-PRJ-001',
            'name' => 'Time Entry Factory Project',
            'status' => 'active',
            'is_billable' => true,
        ]);

        $stage = ProjectStage::query()->first() ?? ProjectStage::query()->create([
            'name' => 'Development',
            'sort_order' => 4,
            'status' => 'active',
        ]);

        $user = User::query()->first();

        $durationMinutes = fake()->numberBetween(30, 240);
        $hourlyRate = (float) fake()->randomFloat(2, 60, 200);

        return [
            'project_id' => $project->id,
            'project_stage_id' => $stage->id,
            'user_id' => $user?->id,
            'entry_date' => now()->toDateString(),
            'duration_minutes' => $durationMinutes,
            'is_billable' => true,
            'hourly_rate' => $hourlyRate,
            'billable_amount' => round(($durationMinutes / 60) * $hourlyRate, 2),
            'notes' => fake()->sentence(),
        ];
    }
}
