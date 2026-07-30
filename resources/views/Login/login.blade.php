@extends('layouts.guest')
@section('title', 'تسجيل الدخول - لوحة الإدارة')

@section('content')
<div class="flex items-center justify-center min-h-[calc(100vh-250px)] px-4">
    <div class="w-full max-w-md bg-bg-card border border-glass-border rounded-[22px] p-8 md:p-10 shadow-[0_8px_30px_rgba(0,0,0,0.3)] backdrop-blur-md relative overflow-hidden group">
        <!-- Decoration -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-gold-light/10 rounded-full blur-3xl pointer-events-none group-hover:bg-gold-light/20 transition-all duration-700"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-blue-accent/10 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-accent/20 transition-all duration-700"></div>

        <h2 class="text-center text-2xl font-bold text-[#4a662e] mb-8 relative z-10">
            <i class="fa-solid fa-lock ml-2"></i>تسجيل الدخول
        </h2>

        <form action="{{ route('login') }}" method="POST" class="relative z-10 space-y-5">
            @csrf

            <div class="space-y-2">
                <label for="email" class="block text-sm font-semibold text-text-secondary">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="admin@example.com" value="{{ old('email') }}" required autofocus>
                @error('email')
                    <span class="block text-center text-sm font-semibold text-red-500 mt-2">{{ $message }}</span>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="password" class="block text-sm font-semibold text-text-secondary">كلمة المرور:</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="••••••••" required>
            </div>

            <button type="submit" class="w-full py-3.5 mt-4 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black text-lg rounded-xl shadow-lg hover:-translate-y-1 hover:shadow-[0_6px_20px_rgba(201,150,58,0.3)] transition-all duration-300">
                <i class="fa-solid fa-arrow-right-to-bracket ml-2"></i> دخول
            </button>
        </form>

        <div class="text-center mt-6 text-sm text-text-secondary relative z-10">
            ليس لديك حساب؟ <a href="{{ route('register') }}" class="text-[#4a662e] font-bold hover:text-[#3d5425] transition-colors">إنشاء حساب جديد</a>
        </div>
    </div>
</div>
@endsection
