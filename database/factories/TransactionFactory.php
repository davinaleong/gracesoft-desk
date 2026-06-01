<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $account = Account::query()->first() ?? Account::query()->create([
            'name' => 'Factory Account',
            'code' => 'FAC-ACC-001',
            'type' => 'bank',
            'currency' => 'SGD',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $category = TransactionCategory::query()->first() ?? TransactionCategory::query()->create([
            'name' => 'Factory Expense',
            'slug' => 'factory-expense',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->first() ?? PaymentMethod::query()->create([
            'name' => 'Factory Bank Transfer',
            'slug' => 'factory-bank-transfer',
            'is_active' => true,
        ]);

        $project = Project::query()->first() ?? Project::query()->create([
            'code' => 'FAC-PRJ-001',
            'name' => 'Factory Project',
            'status' => 'active',
            'is_billable' => false,
        ]);

        $amount = (float) fake()->randomFloat(2, 100, 1000);
        $gst = (float) fake()->randomFloat(2, 0, round($amount * 0.09, 2));

        return [
            'account_id' => $account->id,
            'transaction_category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
            'project_id' => $project->id,
            'type' => 'expense',
            'direction' => 'out',
            'status' => 'completed',
            'transaction_date' => now()->toDateString(),
            'reference' => strtoupper(fake()->bothify('REF-####')),
            'description' => fake()->sentence(),
            'amount' => $amount,
            'gst_amount' => $gst,
            'net_amount' => max(0, $amount - $gst),
        ];
    }
}
