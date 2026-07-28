@extends('layouts.app')
@section('title', 'كورساتي')
@section('page_title', 'كورساتي')

@section('sidebar')
    @include('Admin.users.partials.sidebar')
@endsection

@section('content')
<div class="space-y-8">
    <div class="flex items-center pb-4 border-b border-glass-border">
        <h2 class="text-2xl font-bold text-text-primary relative inline-block">
            كورساتك النشطة
            <span class="absolute -bottom-4 right-0 w-2/3 h-1 bg-gradient-to-r from-gold-light to-transparent rounded-full"></span>
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($myCourses ?? [] as $course)
            <div class="bg-bg-card border border-glass-border rounded-2xl overflow-hidden flex flex-col hover:-translate-y-2 hover:shadow-[0_12px_35px_rgba(0,0,0,0.4)] hover:border-blue-500/30 transition-all duration-400">
                <div class="h-48 border-b border-glass-border shrink-0">
                     <img src="{{ $course->image_path ? asset('storage/' . $course->image_path) : asset('images/default-course.jpg') }}" alt="{{ $course->title }}" class="w-full h-full object-cover">
                </div>
                <div class="p-6 flex-grow flex flex-col justify-center text-center">
                    <h3 class="text-xl font-bold text-text-primary mb-2 leading-relaxed line-clamp-2">{{ $course->title }}</h3>
                </div>
                <a href="{{ route('user.course_details', $course->id) }}" class="block text-center py-4 bg-blue-600 text-white font-bold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-play"></i> ابدأ التعلم
                </a>
            </div>
        @empty
            <div class="col-span-full bg-bg-card border border-glass-border rounded-2xl p-10 text-center">
                <p class="text-lg text-text-secondary font-bold">ليس لديك أي كورسات نشطة حالياً.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
