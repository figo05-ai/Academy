@extends('layouts.app')
@section('title', 'لوحة التحكم')
@section('page_title', 'لوحة التحكم')

@section('sidebar')
    @include('Admin.users.partials.sidebar')
@endsection

@section('content')
@if ($totalEnrolledCourses > 0)
    <div class="space-y-8">
        <div class="flex items-center pb-4 border-b border-glass-border">
            <h2 class="text-2xl font-bold text-text-primary relative inline-block">
                ملخص التقدم
                <span class="absolute -bottom-4 right-0 w-2/3 h-1 bg-gradient-to-r from-gold-light to-transparent rounded-full"></span>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 flex items-center gap-6 shadow-lg">
                <div class="w-16 h-16 shrink-0 rounded-full bg-gold-pale text-[#4a662e] flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div>
                    <div class="text-3xl font-black text-text-primary mb-1">{{ $totalEnrolledCourses }}</div>
                    <div class="text-text-secondary font-semibold text-sm">كورس مشترك به</div>
                </div>
            </div>

            <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 flex items-center gap-6 shadow-lg">
                <div class="w-16 h-16 shrink-0 rounded-full bg-blue-500/10 text-blue-700 flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                    <div class="text-3xl font-black text-text-primary mb-1">{{ $overallProgressPercentage }}%</div>
                    <div class="text-text-secondary font-semibold text-sm">مستوى التقدم العام</div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="text-center max-w-2xl mx-auto py-12">
        <h2 class="text-3xl md:text-4xl font-black text-text-primary mb-4 leading-tight">مرحباً بك في <span class="bg-gradient-to-r from-gold-light to-gold bg-clip-text text-transparent">الأكاديمية</span></h2>
        <p class="text-lg text-text-secondary mb-8">حسابك في انتظار التفعيل. يرجى التواصل معنا للاشتراك في أحد كورساتنا وبدء رحلتك التعليمية.</p>
        
        <div class="bg-bg-card border border-glass-border rounded-2xl p-8 shadow-xl relative overflow-hidden group">
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-gold-light/10 rounded-full blur-3xl pointer-events-none group-hover:bg-gold-light/20 transition-all duration-700"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-blue-500/10 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-500/20 transition-all duration-700"></div>
            
            <h3 class="text-xl font-bold text-[#4a662e] mb-6 flex items-center justify-center gap-3 relative z-10"><i class="fa-solid fa-rocket"></i> مرحباً بك في أكاديمية مكتب فني</h3>
            <p class="text-text-secondary leading-relaxed mb-8 relative z-10">حسابك غير مفعل بعد. لبدء رحلتك التعليمية وتفعيل حسابك، يرجى الاشتراك في أحد كورساتنا أولاً عبر التواصل معنا.</p>
            <a href="https://wa.me/201229004186" target="_blank" class="inline-flex items-center justify-center gap-3 w-full sm:w-auto px-8 py-4 bg-[#25D366] text-white font-black rounded-xl hover:-translate-y-1 hover:bg-[#128C7E] hover:shadow-[0_6px_20px_rgba(37,211,102,0.4)] transition-all relative z-10">
                <i class="fab fa-whatsapp text-xl"></i>
                تواصل معنا للاشتراك
            </a>
        </div>
    </div>
@endif
@endsection
