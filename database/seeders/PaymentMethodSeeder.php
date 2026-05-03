<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Bank Transfer', 'slug' => 'bank-transfer'],
            ['name' => 'Credit Card', 'slug' => 'credit-card'],
            ['name' => 'Cash', 'slug' => 'cash'],
            ['name' => 'Cheque', 'slug' => 'cheque'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['slug' => $method['slug']],
                [
                    'name' => $method['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
