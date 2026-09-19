<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['vendor', 'service']),
            'name' => $this->faker->unique()->words(2, true),
            'status' => 'active',
        ];
    }

    public function vendor(): static
    {
        return $this->state(['type' => 'vendor']);
    }

    public function service(): static
    {
        return $this->state(['type' => 'service']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
