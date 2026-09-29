<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'SGD',
            'payment_terms_days' => 30,
            'gst_rate' => 0,
            'subtotal' => 0,
            'gst_amount' => 0,
            'total' => 0,
        ];
    }

    public function issued(string $number = 'INV-2026-00001', float $total = 100.0): static
    {
        return $this->state([
            'status' => Invoice::STATUS_ISSUED,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'issued_at' => now(),
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
