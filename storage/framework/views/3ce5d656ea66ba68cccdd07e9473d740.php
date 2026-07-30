<?php $__env->startSection('title', 'كورساتي'); ?>
<?php $__env->startSection('page_title', 'كورساتي'); ?>

<?php $__env->startSection('sidebar'); ?>
    <?php echo $__env->make('Admin.users.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-8">
    <div class="flex items-center pb-4 border-b border-glass-border">
        <h2 class="text-2xl font-bold text-text-primary relative inline-block">
            كورساتك النشطة
            <span class="absolute -bottom-4 right-0 w-2/3 h-1 bg-gradient-to-r from-gold-light to-transparent rounded-full"></span>
        </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_1 = true; $__currentLoopData = $myCourses ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="bg-bg-card border border-glass-border rounded-2xl overflow-hidden flex flex-col hover:-translate-y-2 hover:shadow-[0_12px_35px_rgba(0,0,0,0.4)] hover:border-blue-500/30 transition-all duration-400">
                <div class="h-48 border-b border-glass-border shrink-0">
                     <img src="<?php echo e($course->image_path ? asset('storage/' . $course->image_path) : asset('images/default-course.jpg')); ?>" alt="<?php echo e($course->title); ?>" class="w-full h-full object-cover">
                </div>
                <div class="p-6 flex-grow flex flex-col justify-center text-center">
                    <h3 class="text-xl font-bold text-text-primary mb-2 leading-relaxed line-clamp-2"><?php echo e($course->title); ?></h3>
                </div>
                <a href="<?php echo e(route('user.course_details', $course->id)); ?>" class="block text-center py-4 bg-blue-600 text-white font-bold hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-play"></i> ابدأ التعلم
                </a>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="col-span-full bg-bg-card border border-glass-border rounded-2xl p-10 text-center">
                <p class="text-lg text-text-secondary font-bold">ليس لديك أي كورسات نشطة حالياً.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/Admin/users/my_courses.blade.php ENDPATH**/ ?>