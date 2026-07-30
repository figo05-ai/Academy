<?php $__env->startSection('title', 'تفاصيل الكورس: ' . $course->title); ?>
<?php $__env->startSection('page_title', $course->title); ?>

<?php $__env->startSection('sidebar'); ?>
    <?php echo $__env->make('Admin.users.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="flex flex-col lg:flex-row gap-8 items-start min-h-[calc(100vh-200px)]">
    
    <!-- 2. منطقة الفيديو والزراير (اليسار - العرض الأكبر) -->
    <div class="flex-1 w-full flex flex-col gap-6 min-h-0">
        <!-- الفيديو -->
        <div class="relative w-full aspect-video bg-black rounded-2xl border border-glass-border flex items-center justify-center overflow-hidden shadow-2xl group" <?php if($course->image_path): ?> style="background-image: url('<?php echo e(asset('storage/' . $course->image_path)); ?>'); background-size: cover; background-position: center;" <?php endif; ?>>
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
            <?php 
                $globalIndex = 0; 
                $categoryIndex = 0;
            ?>
            <?php $__currentLoopData = $sessions->groupBy('category_name'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryName => $groupedSessions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
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
                ?>

                <div class="category-group mb-2">
                    <?php if($categoryName): ?>
                        <button class="w-full px-4 py-3 bg-black/40 hover:bg-black/60 border border-glass-border rounded-xl shadow-sm flex items-center justify-between transition-colors category-toggle" data-target="category-<?php echo e($categoryIndex); ?>">
                            <h4 class="text-sm font-bold text-[#4a662e]"><i class="fa-solid fa-folder-open mr-2 opacity-70"></i> <?php echo e($categoryName); ?></h4>
                            <i class="fa-solid fa-chevron-down text-text-muted transition-transform duration-300 <?php echo e($isExpanded ? 'rotate-180' : ''); ?>"></i>
                        </button>
                    <?php endif; ?>
                    
                    <div id="category-<?php echo e($categoryIndex); ?>" class="category-content <?php echo e($categoryName ? 'mt-2' : ''); ?> flex flex-col gap-3 <?php echo e($isExpanded || !$categoryName ? '' : 'hidden'); ?>">
                        <?php $__currentLoopData = $groupedSessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $session): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $is_unlocked = ($globalIndex <= $unlockedSessionIndex);
                                $is_active = ($globalIndex == $unlockedSessionIndex);
                                $is_completed = $session->user_completed;
                            ?>
                            <div class="group flex items-center justify-between p-4 rounded-xl border border-transparent transition-all duration-300 v-session-item
                                <?php echo e($is_completed ? 'completed text-text-secondary' : ''); ?>

                                <?php echo e($is_unlocked ? 'unlocked cursor-pointer hover:bg-white/5' : 'locked opacity-50 cursor-not-allowed'); ?>

                                <?php echo e($is_active ? 'active bg-gold-pale border-gold-light' : ''); ?>"
                                data-session-id="<?php echo e($session->id); ?>"
                                data-session-title="<?php echo e($session->title); ?>"
                                data-drive-link="<?php echo e($session->drive_link); ?>">

                                <div class="flex items-center gap-4 overflow-hidden">
                                    <span class="w-9 h-9 shrink-0 flex items-center justify-center rounded-full font-bold transition-colors
                                        <?php echo e($is_active ? 'bg-gold-light text-text-primary' : 'border border-text-secondary text-text-primary group-hover:border-gold-light'); ?>

                                    "><?php echo e($globalIndex + 1); ?></span>
                                    <span class="font-semibold text-sm truncate <?php echo e($is_completed ? 'line-through' : 'text-text-primary'); ?>" title="<?php echo e($session->title); ?>"><?php echo e($session->title); ?></span>
                                </div>

                                <div class="shrink-0 ml-2 v-session-icon">
                                    <?php if($is_completed): ?>
                                        <i class="fa-solid fa-check text-green-700"></i>
                                    <?php elseif(!$is_unlocked): ?>
                                        <i class="fa-solid fa-lock text-text-secondary"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $globalIndex++; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php $categoryIndex++; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>

<script nonce="<?php echo e($csp_nonce); ?>">
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
                                'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/Admin/users/course_details.blade.php ENDPATH**/ ?>