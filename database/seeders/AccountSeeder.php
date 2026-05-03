<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Main Operating Account', 'code' => 'BANK-MAIN', 'type' => 'bank'],
            ['name' => 'Petty Cash', 'code' => 'CASH-PETTY', 'type' => 'cash'],
            ['name' => 'Corporate Card', 'code' => 'CARD-CORP', 'type' => 'card'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'currency' => 'SGD',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
