<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'billing_email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'tax_id' => fake()->optional()->bothify('20######?'),
            'currency' => 'SGD',
            'default_hourly_rate' => null,
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }

    public function withRate(float $rate): static
    {
        return $this->state(['default_hourly_rate' => $rate]);
    }
}
