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

        // الآن، نقوم بإنشاء أو تحديث حساب الأدمن المحدد
        User::updateOrCreate(
            ['email' => 'gorge@gmail.com'], // البحث عن المستخدم بهذا الإيميل
            [
                'name' => 'Gorge Admin',
                'password' => Hash::make('12#qW4568'), // كلمة المرور التي طلبتها
                'is_admin' => true, // تحديد هذا المستخدم كأدمن
            ]
        );
    }
}
