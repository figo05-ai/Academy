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
            ['email' => 'admin@academy.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@123456'),
                'is_admin' => true, // admin's permission
            ]
        );

        // Create Regular User
        User::firstOrCreate(
            ['email' => 'user@academy.com'],
            [
                'name' => 'Regular Student',
                'password' => Hash::make('User@123456'),
                'is_admin' => false, // normal user
            ]
        );
    }
}
