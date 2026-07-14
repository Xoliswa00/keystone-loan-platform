<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Bootstraps the first admin account from .env (ADMIN_EMAIL / ADMIN_PASSWORD)
     * so a fresh install has someone able to log in and use Staff Management to
     * add everyone else. Safe to re-run — it only touches that one account.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('AdminSeeder skipped — set ADMIN_EMAIL and ADMIN_PASSWORD in .env to bootstrap the first admin.');

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['system_role' => 'admin'])->save();

            return;
        }

        $user = User::create([
            'name' => 'System Administrator',
            'email' => $email,
            'password' => $password,
            'address' => 'Head Office',
            'phone' => '0'.random_int(100000000, 999999999),
            'ID_Number' => (string) random_int(1000000000000, 9999999999999),
            'salary_payment_day' => 1,
            'ID_copy' => 'n/a',
            'email_verified_at' => now(),
        ]);

        $user->forceFill(['system_role' => 'admin'])->save();
    }
}
