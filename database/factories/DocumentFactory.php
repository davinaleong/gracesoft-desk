<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'disk' => 's3',
            'path' => 'documents/'.$this->faker->uuid().'/'.$this->faker->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(1024, 5_000_000),
        ];
    }
}
