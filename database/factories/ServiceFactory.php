<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'name' => $this->faker->words(3, true),
            'plan' => $this->faker->optional()->randomElement(['Free', 'Starter', 'Business', 'Enterprise']),
            'category' => $this->faker->randomElement(['storage', 'communication', 'design', 'dev_tools', 'security', 'productivity', 'other']),
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
