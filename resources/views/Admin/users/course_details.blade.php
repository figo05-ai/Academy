@extends('layouts.app')
@section('title', 'تفاصيل الكورس: ' . $course->title)
@section('page_title', $course->title)

@section('sidebar')
    @include('Admin.users.partials.sidebar')
@endsection

@section('content')
<div class="flex flex-col lg:flex-row gap-8 items-start min-h-[calc(100vh-200px)]">
    
    <!-- 2. منطقة الفيديو والزراير (اليسار - العرض الأكبر) -->
    <div class="flex-1 w-full flex flex-col gap-6 min-h-0">
        <!-- الفيديو -->
        <div class="relative w-full aspect-video bg-black rounded-2xl border border-glass-border flex items-center justify-center overflow-hidden shadow-2xl group" @if($course->image_path) style="background-image: url('{{ asset('storage/' . $course->image_path) }}'); background-size: cover; background-position: center;" @endif>
            <div class="absolute inset-0 bg-black/50 group-hover:bg-black/40 transition-colors duration-500 z-10"></div>
            <i class="fa-solid fa-play text-7xl text-white/80 cursor-pointer hover:text-white hover:scale-110 transition-all duration-300 z-20"></i>
        </div>

        <!-- الزراير -->
        <div id="session-actions-container" class="w-full hidden">
            <a href="#" id="btn-download" target="_blank" class="flex items-center justify-center w-full py-4 bg-bg-card border border-glass-border rounded-xl font-bold text-lg text-text-primary hover:bg-white/5 hover:border-gold-light hover:text-[#4a662e] transition-all shadow-lg">
                <i class="fa-solid fa-download ml-3"></i> تحميل المحاضرة
            </a>
        </div>
    </div>

    <!-- 1. القائمة (اليمين - المحاضرات) -->
    <div class="w-full lg:w-96 shrink-0 bg-bg-card border border-glass-border rounded-2xl flex flex-col h-[500px] lg:h-full shadow-lg">
        <div class="p-5 border-b border-glass-border shrink-0">
            <h3 class="text-xl font-bold text-text-primary flex items-center gap-2"><i class="fa-solid fa-list-check text-[#4a662e]"></i> قائمة المحاضرات</h3>
        </div>

        <div class="p-4 overflow-y-auto flex flex-col gap-3 custom-scrollbar flex-1">
            @php 
                $globalIndex = 0; 
                $categoryIndex = 0;
            @endphp
            @foreach ($sessions->groupBy('category_name') as $categoryName => $groupedSessions)
                @php
                    $containsActiveSession = false;
                    $tempGlobal = $globalIndex;
                    foreach ($groupedSessions as $s) {
                        if ($tempGlobal == $unlockedSessionIndex) {
                            $containsActiveSession = true;
                            break;
                        }
                        $tempGlobal++;
                    }
                    $isExpanded = $containsActiveSession;
                    if ($unlockedSessionIndex >= count($sessions) && $categoryIndex == count($sessions->groupBy('category_name')) - 1) {
                        $isExpanded = true;
                    }
                @endphp

                <div class="category-group mb-2">
                    @if($categoryName)
                        <button class="w-full px-4 py-3 bg-black/40 hover:bg-black/60 border border-glass-border rounded-xl shadow-sm flex items-center justify-between transition-colors category-toggle" data-target="category-{{ $categoryIndex }}">
                            <h4 class="text-sm font-bold text-[#4a662e]"><i class="fa-solid fa-folder-open mr-2 opacity-70"></i> {{ $categoryName }}</h4>
                            <i class="fa-solid fa-chevron-down text-text-muted transition-transform duration-300 {{ $isExpanded ? 'rotate-180' : '' }}"></i>
                        </button>
                    @endif
                    
                    <div id="category-{{ $categoryIndex }}" class="category-content {{ $categoryName ? 'mt-2' : '' }} flex flex-col gap-3 {{ $isExpanded || !$categoryName ? '' : 'hidden' }}">
                        @foreach ($groupedSessions as $session)
                            @php
                                $is_unlocked = ($globalIndex <= $unlockedSessionIndex);
                                $is_active = ($globalIndex == $unlockedSessionIndex);
                                $is_completed = $session->user_completed;
                            @endphp
                            <div class="group flex items-center justify-between p-4 rounded-xl border border-transparent transition-all duration-300 v-session-item
                                {{ $is_completed ? 'completed text-text-secondary' : '' }}
                                {{ $is_unlocked ? 'unlocked cursor-pointer hover:bg-white/5' : 'locked opacity-50 cursor-not-allowed' }}
                                {{ $is_active ? 'active bg-gold-pale border-gold-light' : '' }}"
                                data-session-id="{{ $session->id }}"
                                data-session-title="{{ $session->title }}"
                                data-drive-link="{{ $session->drive_link }}">

                                <div class="flex items-center gap-4 overflow-hidden">
                                    <span class="w-9 h-9 shrink-0 flex items-center justify-center rounded-full font-bold transition-colors
                                        {{ $is_active ? 'bg-gold-light text-text-primary' : 'border border-text-secondary text-text-primary group-hover:border-gold-light' }}
                                    ">{{ $globalIndex + 1 }}</span>
                                    <span class="font-semibold text-sm truncate {{ $is_completed ? 'line-through' : 'text-text-primary' }}" title="{{ $session->title }}">{{ $session->title }}</span>
                                </div>

                                <div class="shrink-0 ml-2 v-session-icon">
                                    @if($is_completed)
                                        <i class="fa-solid fa-check text-green-700"></i>
                                    @elseif(!$is_unlocked)
                                        <i class="fa-solid fa-lock text-text-secondary"></i>
                                    @endif
                                </div>
                            </div>
                            @php $globalIndex++; @endphp
                        @endforeach
                    </div>
                </div>
                @php $categoryIndex++; @endphp
            @endforeach
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>

<script nonce="{{ $csp_nonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const sessionsList = document.querySelector('.custom-scrollbar');
    const actionsContainer = document.getElementById('session-actions-container');
    const downloadBtn = document.getElementById('btn-download');

    // Accordion Logic for Categories
    document.querySelectorAll('.category-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetContent = document.getElementById(targetId);
            const icon = this.querySelector('i.fa-chevron-down');
            const isCurrentlyHidden = targetContent.classList.contains('hidden');

            // Close all other categories
            document.querySelectorAll('.category-content').forEach(content => {
                if (content.id !== targetId && !content.classList.contains('always-open')) {
                    content.classList.add('hidden');
                }
            });
            document.querySelectorAll('.category-toggle i.fa-chevron-down').forEach(i => {
                if (i !== icon) i.classList.remove('rotate-180');
            });

            // Toggle current
            if (isCurrentlyHidden) {
                targetContent.classList.remove('hidden');
                if(icon) icon.classList.add('rotate-180');
            } else {
                targetContent.classList.add('hidden');
                if(icon) icon.classList.remove('rotate-180');
            }
        });
    });

    function updateContent(sessionElement) {
        document.querySelectorAll('.v-session-item').forEach(el => {
            el.classList.remove('active', 'bg-gold-pale', 'border-gold-light');
            el.querySelector('span.w-9').classList.remove('bg-gold-light', 'text-text-primary');
            el.querySelector('span.w-9').classList.add('border', 'border-text-secondary', 'text-text-primary');
        });

        sessionElement.classList.add('active', 'bg-gold-pale', 'border-gold-light');
        sessionElement.querySelector('span.w-9').classList.remove('border', 'border-text-secondary', 'text-text-primary');
        sessionElement.querySelector('span.w-9').classList.add('bg-gold-light', 'text-text-primary');

        if (sessionElement.dataset.driveLink) {
            actionsContainer.classList.remove('hidden');
            downloadBtn.href = sessionElement.dataset.driveLink;
        } else {
            actionsContainer.classList.add('hidden');
        }
    }

    if (sessionsList) {
        sessionsList.addEventListener('click', function(e) {
            const item = e.target.closest('.v-session-item');
            if (!item) return;

            if (item.classList.contains('locked')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'غير مصرح!',
                    text: 'يجب عليك تحميل المحاضرة السابقة أولاً لتتمكن من فتح هذه المحاضرة.',
                    confirmButtonColor: '#d3b574',
                    background: '#0e1426',
                    color: '#edf2ff'
                });
                return;
            }

            updateContent(item);
        });
    }

    const firstActive = document.querySelector('.v-session-item.active') || document.querySelector('.v-session-item.unlocked');
    if (firstActive) {
        updateContent(firstActive);
    }

    if (downloadBtn) {
        downloadBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const driveLink = this.getAttribute('href');
            if (driveLink === '#' || !driveLink) return;

            const activeSession = document.querySelector('.v-session-item.active');
            const instructorWhatsapp = "201000000000"; 

            Swal.fire({
                title: 'تنبيه هام!',
                html: `
                    <div style="margin-bottom: 20px; font-size: 16px; color: #7a92b8;">
                        هذه المحاضرة مشفرة، برجاء التواصل مع الإنستراكتور للحصول على الصلاحية وكود فك التشفير لتتمكن من مشاهدتها.
                    </div>
                    <a href="https://wa.me/${instructorWhatsapp}" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; background: #25D366; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-bottom: 10px; transition: all 0.3s;">
                        <i class="fa-brands fa-whatsapp" style="font-size: 22px;"></i> تواصل عبر واتساب
                    </a>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'انتقل للمحاضرة',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#d3b574',
                cancelButtonColor: '#eb5757',
                background: '#0e1426',
                color: '#edf2ff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(driveLink, '_blank');
                    unlockNextSession(activeSession);
                    
                    if (activeSession.dataset.sessionId) {
                        fetch(`/user/sessions/${activeSession.dataset.sessionId}/complete`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            }
                        }).then(res => res.json()).then(data => console.log(data)).catch(err => console.error(err));
                    }
                }
            });
        });
    }

    function unlockNextSession(currentSession) {
        currentSession.classList.add('completed', 'text-text-secondary');
        currentSession.querySelector('.font-semibold').classList.add('line-through');
        currentSession.querySelector('.font-semibold').classList.remove('text-text-primary');
        const iconContainer = currentSession.querySelector('.v-session-icon');
        if (iconContainer) {
            iconContainer.innerHTML = '<i class="fa-solid fa-check text-green-700"></i>';
        }

        const nextSession = currentSession.nextElementSibling;
        if (nextSession && nextSession.classList.contains('locked')) {
            nextSession.classList.remove('locked', 'opacity-50', 'cursor-not-allowed');
            nextSession.classList.add('unlocked', 'cursor-pointer', 'hover:bg-white/5');

            const nextIconContainer = nextSession.querySelector('.v-session-icon');
            if (nextIconContainer) {
                nextIconContainer.innerHTML = '';
            }
        }
    }
});
</script>
<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(201, 150, 58, 0.3);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(201, 150, 58, 0.5);
}
</style>
@endsection
