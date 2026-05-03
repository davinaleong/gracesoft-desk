<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Invoice Income', 'slug' => 'invoice-income', 'type' => 'income'],
            ['name' => 'Service Retainer', 'slug' => 'service-retainer', 'type' => 'income'],
            ['name' => 'Software & SaaS', 'slug' => 'software-saas', 'type' => 'expense'],
            ['name' => 'Payroll', 'slug' => 'payroll', 'type' => 'expense'],
            ['name' => 'Office Expense', 'slug' => 'office-expense', 'type' => 'expense'],
        ];

        foreach ($categories as $category) {
            TransactionCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
