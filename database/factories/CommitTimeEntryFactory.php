<?php

namespace Database\Factories;

use App\Models\CommitTimeEntry;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitTimeEntry>
 */
class CommitTimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sha' => fake()->sha1(),
            'branch' => 'main',
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'committed_at' => now()->subMinutes(fake()->numberBetween(1, 1440)),
            'message' => fake()->sentence(),
            'additions' => fake()->numberBetween(0, 200),
            'deletions' => fake()->numberBetween(0, 50),
            'changed_files' => fake()->numberBetween(1, 10),
            'status' => 'pending',
        ];
    }
}
