<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            'name' => env('ADMIN_NAME', 'GraceSoft Admin'),
            'email' => env('ADMIN_EMAIL', 'admin@gracesoft.dev'),
            'password' => Hash::make(env('ADMIN_TEMP_PASSWORD', 'ChangeMe123!')),
            'must_change_password' => true,
            'password_changed_at' => null,
        ];

        $existing = User::query()->first();

        if ($existing) {
            $existing->update($attributes);
        } else {
            User::create($attributes);
        }
    }
}
