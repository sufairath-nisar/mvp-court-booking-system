<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default admin account.
     *
     * Admins can ONLY be created here (per spec) — there is no admin registration endpoint.
     * Credentials are configurable via the ADMIN_EMAIL / ADMIN_PASSWORD env vars.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@courtbooking.test')],
            [
                'name'     => 'System Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Password123!')),
                'role'     => UserRole::ADMIN,
            ]
        );
    }
}
