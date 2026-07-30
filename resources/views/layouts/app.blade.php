<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>@yield('title', 'لوحة التحكم - أكاديمية المكتب الفني')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="selection:bg-gold selection:text-text-primary overflow-hidden h-screen flex" x-data="{ sidebarOpen: false }">

    <!-- Mobile sidebar backdrop -->
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-bg-primary/80 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'" class="fixed lg:static inset-y-0 right-0 z-50 w-72 bg-bg-card border-l border-glass-border shadow-2xl lg:shadow-none transition-transform duration-300 ease-in-out flex flex-col">
        
        <!-- Sidebar Header -->
        <div class="h-[100px] flex items-center px-6 border-b border-glass-border shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gold-light to-gold flex items-center justify-center text-lg font-black text-text-primary shadow-[0_2px_10px_rgba(201,150,58,0.3)]">
                    G
                </div>
                <span class="text-xl font-bold bg-gradient-to-br from-gold-light to-gold bg-clip-text text-transparent">Gorge</span>
            </div>
            <!-- Close button on mobile -->
            <button @click="sidebarOpen = false" class="lg:hidden mr-auto text-text-muted hover:text-text-primary transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="flex-1 overflow-y-auto py-6 px-4 space-y-2">
            <!-- Example Links, you can replace with a yield or component -->
            @yield('sidebar')
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-6 border-t border-glass-border shrink-0">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center">
                    <i class="fas fa-user text-[#4a662e]"></i>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-text-primary truncate">{{ Auth::user()?->name ?? 'المستخدم' }}</p>
                    <p class="text-xs text-text-muted truncate">{{ Auth::user()?->email ?? '' }}</p>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-2 rounded-lg bg-red-500/10 text-red-700 hover:bg-red-500/20 transition-colors font-semibold text-sm">
                    <i class="fas fa-sign-out-alt"></i>
                    تسجيل الخروج
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        
        <!-- Top Navbar -->
        <header class="h-[100px] shrink-0 border-b border-glass-border bg-bg-primary/50 backdrop-blur-xl px-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <!-- Hamburger -->
                <button @click="sidebarOpen = true" class="lg:hidden text-text-secondary hover:text-text-primary transition">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-text-primary">@yield('page_title', 'لوحة التحكم')</h1>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="/" class="hidden sm:flex items-center gap-2 text-sm text-text-secondary hover:text-[#4a662e] transition font-semibold">
                    <i class="fas fa-home"></i>
                    الرئيسية
                </a>
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>
    </div>

    @yield('scripts')
</body>
</html>
