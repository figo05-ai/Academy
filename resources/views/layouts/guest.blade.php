<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>@yield('title', 'أكاديمية المكتب الفني')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="selection:bg-gold selection:text-text-primary">

    <!-- Navbar -->
    <nav class="sticky top-0 z-50 h-[100px] px-4 md:px-8 flex items-center justify-between bg-bg-primary/75 backdrop-blur-xl border-b border-glass-border">
        <div class="flex items-center gap-4">
            <div class="w-14 h-10 rounded-xl bg-gradient-to-br from-gold-light to-gold flex items-center justify-center text-lg font-black text-text-primary shadow-[0_2px_16px_rgba(201,150,58,0.35)] shrink-0">
                G
            </div>
            <a href="/" class="text-xl font-bold bg-gradient-to-br from-gold-light to-gold bg-clip-text text-transparent tracking-wide">
                Gorge
            </a>
        </div>

        <a href="{{ route('register') }}" class="hidden md:flex absolute left-1/2 -translate-x-1/2 w-32 h-12 rounded-full bg-gradient-to-br from-gold-light to-gold items-center justify-center text-lg font-black text-text-primary shadow-[0_2px_16px_rgba(201,150,58,0.35)] hover:scale-105 hover:-translate-y-1 transition-all duration-300">
            سجل الآن
        </a>

        <div class="flex items-center gap-4 md:gap-6">
            @auth
                <a href="{{ route('user.dashboard') }}" class="text-text-primary hover:text-[#4a662e] transition font-semibold">لوحة التحكم</a>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-red-700 hover:text-red-300 transition font-semibold">تسجيل الخروج</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="text-text-secondary hover:text-text-primary transition font-semibold">تسجيل الدخول</a>
            @endauth
        </div>
    </nav>

    <!-- Main Content -->
    <main class="relative z-10 w-full min-h-[calc(100vh-200px)]">
        @yield('content')
    </main>

    <!-- Contacts Section / Footer -->
    <footer class="mt-24 border-t border-glass-border bg-bg-secondary/50 backdrop-blur-md pt-12 pb-8">
        <div class="max-w-6xl mx-auto px-6 text-center">
            
            <div class="flex flex-wrap justify-center gap-4 max-w-4xl mx-auto mb-8">
                <a href="mailto:info@landscape-technical-office.com" class="group flex items-center gap-3 py-2.5 px-4 pr-3 bg-bg-card border border-glass-border rounded-full font-bold text-sm text-text-primary shadow-lg hover:-translate-y-1 hover:scale-105 hover:border-gold-light hover:text-[#4a662e] hover:shadow-[0_8px_25px_rgba(240,199,106,0.15)] transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center text-lg group-hover:bg-gold-light group-hover:text-text-primary transition-colors"><i class="fa-solid fa-envelope"></i></div>
                    البريد الإلكتروني
                </a>
                
                <a href="tel:+201229004186" class="group flex items-center gap-3 py-2.5 px-4 pr-3 bg-bg-card border border-glass-border rounded-full font-bold text-sm text-text-primary shadow-lg hover:-translate-y-1 hover:scale-105 hover:border-blue-accent hover:text-blue-accent hover:shadow-[0_8px_25px_rgba(59,126,245,0.15)] transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center text-lg group-hover:bg-blue-accent group-hover:text-white transition-colors"><i class="fa-solid fa-phone"></i></div>
                    الهاتف
                </a>

                <a href="https://www.youtube.com/@landscapetechnicaloffice" target="_blank" class="group flex items-center gap-3 py-2.5 px-4 pr-3 bg-bg-card border border-glass-border rounded-full font-bold text-sm text-text-primary shadow-lg hover:-translate-y-1 hover:scale-105 hover:border-red-500 hover:text-red-500 hover:shadow-[0_8px_25px_rgba(255,0,0,0.15)] transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center text-lg group-hover:bg-red-500 group-hover:text-white transition-colors"><i class="fa-brands fa-youtube"></i></div>
                    يوتيوب
                </a>
                
                <a href="https://www.tiktok.com/@eslamelshaer83" target="_blank" class="group flex items-center gap-3 py-2.5 px-4 pr-3 bg-bg-card border border-glass-border rounded-full font-bold text-sm text-text-primary shadow-lg hover:-translate-y-1 hover:scale-105 hover:border-[#00f2fe] hover:text-[#00f2fe] transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center text-lg group-hover:bg-bg-primary group-hover:text-white group-hover:shadow-[inset_0_0_5px_#00f2fe] transition-colors"><i class="fa-brands fa-tiktok"></i></div>
                    تيك توك
                </a>
                
                <a href="https://www.instagram.com/Landscape_technical_office" target="_blank" class="group flex items-center gap-3 py-2.5 px-4 pr-3 bg-bg-card border border-glass-border rounded-full font-bold text-sm text-text-primary shadow-lg hover:-translate-y-1 hover:scale-105 hover:border-pink-500 hover:text-pink-500 hover:shadow-[0_8px_25px_rgba(225,48,108,0.15)] transition-all duration-300">
                    <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center text-lg group-hover:bg-gradient-to-tr group-hover:from-yellow-400 group-hover:via-pink-500 group-hover:to-purple-500 group-hover:text-white group-hover:border-transparent transition-all"><i class="fa-brands fa-instagram"></i></div>
                    إنستجرام
                </a>
            </div>

            <p class="text-text-muted">&copy; 2026 أكاديمية المكتب الفني. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <!-- Floating Whatsapp (Global) -->
    <a href="https://wa.me/201229004186" target="_blank" x-data="{ show: false }" @scroll.window="show = (window.pageYOffset > 50)" :class="show ? 'translate-x-0 opacity-100' : 'translate-x-24 opacity-0'" class="fixed bottom-10 right-8 w-16 h-16 bg-[#25D366] text-white rounded-full flex items-center justify-center text-4xl shadow-[0_4px_15px_rgba(37,211,102,0.4)] z-50 hover:bg-[#20b858] hover:scale-110 transition-all duration-500 group">
        <i class="fa-brands fa-whatsapp"></i>
        <span class="absolute -bottom-8 bg-bg-card text-text-primary text-xs font-bold px-3 py-1 rounded-full border border-glass-border shadow-lg opacity-0 group-hover:opacity-100 group-hover:text-[#4a662e] group-hover:border-gold/40 transition-all whitespace-nowrap pointer-events-none">تواصل معنا</span>
    </a>
</body>
</html>
