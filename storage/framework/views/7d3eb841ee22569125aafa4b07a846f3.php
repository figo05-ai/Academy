<?php $__env->startSection('title', 'استكشف الكورسات'); ?>
<?php $__env->startSection('page_title', 'استكشف الكورسات'); ?>

<?php $__env->startSection('sidebar'); ?>
    <?php echo $__env->make('Admin.users.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="text-center mb-10">
    <h2 class="text-3xl font-black text-text-primary mb-3">استكشف <span class="bg-gradient-to-r from-gold-light to-gold bg-clip-text text-transparent">كورساتنا</span></h2>
    <p class="text-text-secondary text-lg max-w-2xl mx-auto">أنت على بعد خطوة واحدة من بدء رحلتك التعليمية. اختر الكورس الذي يناسبك وتواصل معنا للاشتراك.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" x-data="{ modalOpen: false, activeCourse: null }">
    <?php $__empty_1 = true; $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="bg-bg-card border border-glass-border rounded-2xl overflow-hidden flex flex-col group hover:-translate-y-2 hover:shadow-[0_12px_35px_rgba(0,0,0,0.4)] hover:border-gold-light/40 transition-all duration-400 cursor-pointer">
            <div class="h-52 overflow-hidden border-b border-glass-border shrink-0">
                <img src="<?php echo e($course->image_path ? asset('storage/' . $course->image_path) : asset('images/default-course.jpg')); ?>" alt="<?php echo e($course->title); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <h3 class="text-xl font-bold text-text-primary mb-3"><?php echo e($course->title); ?></h3>
                <p class="text-sm text-text-secondary leading-relaxed mb-6 flex-grow"><?php echo e(Str::limit($course->description, 120)); ?></p>
                <div class="pt-4 border-t border-glass-border border-dashed flex items-center justify-between mt-auto">
                    <div class="text-2xl font-black text-[#4a662e]"><?php echo e($course->price); ?> <span class="text-sm font-normal text-text-secondary">EGP</span></div>
                    <button @click="activeCourse = <?php echo e(json_encode($course)); ?>; modalOpen = true" class="px-5 py-2.5 bg-gradient-to-br from-gold-light to-gold text-text-primary font-bold rounded-xl hover:scale-105 transition-transform shadow-md hover:shadow-gold/30 text-sm">عرض التفاصيل</button>
                </div>
            </div>
            <a href="https://wa.me/201229004186" target="_blank" class="block text-center py-4 bg-[#25D366] text-white font-bold hover:bg-[#128C7E] transition-colors flex items-center justify-center gap-2">
                <i class="fab fa-whatsapp text-lg"></i> تواصل للاشتراك
            </a>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="col-span-full bg-bg-card border border-glass-border rounded-2xl p-10 text-center">
            <p class="text-lg text-text-secondary font-bold">لا توجد كورسات متاحة حالياً أو أنك مشترك في جميع الكورسات.</p>
        </div>
    <?php endif; ?>

    <!-- Modal -->
    <div x-show="modalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4" style="display: none;">
        <div x-show="modalOpen" x-transition.opacity class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="modalOpen = false"></div>
        <div x-show="modalOpen" x-transition.scale.origin.bottom class="relative bg-bg-card border border-glass-border rounded-2xl w-full max-w-xl p-8 shadow-2xl">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-glass-border">
                <h3 class="text-xl font-bold text-[#4a662e]" x-text="activeCourse?.title"></h3>
                <button @click="modalOpen = false" class="text-2xl text-text-secondary hover:text-red-700 transition-colors">&times;</button>
            </div>
            <div class="mb-8">
                <p class="text-text-secondary leading-relaxed text-lg mb-6" x-text="activeCourse?.description"></p>
                <div class="text-center">
                    <span class="text-5xl font-black bg-gradient-to-r from-gold-light to-gold bg-clip-text text-transparent" x-text="activeCourse?.price"></span>
                    <span class="text-xl font-bold text-text-secondary ml-2">EGP</span>
                </div>
            </div>
            <a href="https://wa.me/201229004186" target="_blank" class="flex w-full items-center justify-center gap-3 py-4 bg-[#25D366] text-white font-black rounded-xl hover:-translate-y-1 hover:bg-[#128C7E] hover:shadow-[0_6px_20px_rgba(37,211,102,0.4)] transition-all">
                <i class="fab fa-whatsapp text-xl"></i>
                تواصل للاشتراك
            </a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/Admin/users/explore_courses.blade.php ENDPATH**/ ?>