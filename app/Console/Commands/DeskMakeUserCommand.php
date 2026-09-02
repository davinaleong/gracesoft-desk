<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('desk:make-user
    {--name= : Account name}
    {--email= : Account email}
    {--password= : Account password (omit to be prompted, which avoids it landing in shell history)}
    {--must-change-password : Force a password change on next login}
    {--force : Overwrite the existing account without confirming}')]
#[Description('Create or replace the single GraceSoft Desk admin account')]
class DeskMakeUserCommand extends Command
{
    public function handle(): int
    {
        $existing = User::query()->first();

        if ($existing && ! $this->option('force')) {
            $this->warn("An account already exists: {$existing->name} <{$existing->email}>.");

            if (! $this->confirm('Overwrite it with new credentials?', false)) {
                $this->info('Cancelled. No changes made.');

                return self::SUCCESS;
            }
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');
        $passwordConfirmation = $this->option('password') ? $password : $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $mustChangePassword = (bool) $this->option('must-change-password');

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => $mustChangePassword,
            'password_changed_at' => $mustChangePassword ? null : now(),
            // Overwriting credentials is also how a locked-out admin recovers
            // access, so clear 2FA state and the failed-login counter too.
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'failed_login_attempts' => 0,
        ];

        if ($existing) {
            $existing->update($attributes);
            $this->info("Updated account: {$name} <{$email}>.");
        } else {
            User::create($attributes);
            $this->info("Created account: {$name} <{$email}>.");
        }

        return self::SUCCESS;
    }
}
