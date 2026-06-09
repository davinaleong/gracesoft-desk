<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'category' => $this->faker->randomElement(['telco', 'cloud', 'saas', 'professional_services', 'utilities', 'other']),
            'website' => $this->faker->optional()->url(),
            'support_url' => $this->faker->optional()->url(),
            'account_number' => $this->faker->optional()->bothify('ACC-####'),
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
