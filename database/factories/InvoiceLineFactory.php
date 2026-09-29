<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'type' => InvoiceLine::TYPE_MANUAL,
            'description' => fake()->sentence(4),
            'quantity' => 1,
            'unit_price' => 100,
            'amount' => 100,
            'gst_amount' => 0,
        ];
    }
}
