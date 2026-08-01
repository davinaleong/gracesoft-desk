<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.strtoupper(fake()->bothify('??-####')),
            'name' => fake()->sentence(3),
            'status' => 'active',
            'description' => fake()->paragraph(),
            'starts_on' => now()->subDays(10)->toDateString(),
            'ends_on' => now()->addDays(20)->toDateString(),
            'is_billable' => true,
            'hourly_rate' => (float) fake()->randomFloat(2, 60, 200),
        ];
    }
}
