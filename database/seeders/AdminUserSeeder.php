<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // أولاً، نتأكد من أن لا يوجد أي مستخدم آخر لديه صلاحيات الأدمن
        User::where('is_admin', true)->update(['is_admin' => false]);

        // Now create or update admin user using environment configuration
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'SecureAdminPass!' . rand(1000, 9999));

        User::updateOrCreate(
            ['email' => env('ADMIN_DEFAULT_EMAIL', 'gorge@gmail.com')],
            [
                'name' => 'Gorge Admin',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
            ]
        );
    }
}
