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
        $email = env('ADMIN_EMAIL', 'admin@gracesoft.dev');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'GraceSoft Admin'),
                'password' => Hash::make(env('ADMIN_TEMP_PASSWORD', 'ChangeMe123!')),
                'must_change_password' => true,
                'password_changed_at' => null,
            ]
        );
    }
}
