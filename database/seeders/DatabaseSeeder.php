<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Create Admin User
        User::firstOrCreate(
            ['email' => env('ADMIN_DEFAULT_EMAIL', 'admin@academy.com')],
            [
                'name' => 'System Administrator',
                'password' => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'SecureAdminPass!' . rand(1000, 9999))),
                'is_admin' => true,
            ]
        );

        // Create Regular User
        User::firstOrCreate(
            ['email' => env('USER_DEFAULT_EMAIL', 'user@academy.com')],
            [
                'name' => 'Regular Student',
                'password' => Hash::make(env('USER_DEFAULT_PASSWORD', 'SecureUserPass!' . rand(1000, 9999))),
                'is_admin' => false,
            ]
        );
    }
}
