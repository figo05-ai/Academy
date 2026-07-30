<a href="<?php echo e(route('user.dashboard')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-semibold <?php echo e(request()->routeIs('user.dashboard') ? 'bg-glass-bg text-[#4a662e]' : 'text-text-secondary hover:bg-glass-bg hover:text-[#4a662e]'); ?>">
    <i class="fa-solid fa-chart-line w-5 text-center"></i>
    لوحة التحكم
</a>
<a href="<?php echo e(route('user.my_courses')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-semibold <?php echo e(request()->routeIs('user.my_courses') ? 'bg-glass-bg text-[#4a662e]' : 'text-text-secondary hover:bg-glass-bg hover:text-[#4a662e]'); ?>">
    <i class="fa-solid fa-book-open w-5 text-center"></i>
    كورساتي
</a>
<a href="<?php echo e(route('user.explore_courses')); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-semibold <?php echo e(request()->routeIs('user.explore_courses') ? 'bg-glass-bg text-[#4a662e]' : 'text-text-secondary hover:bg-glass-bg hover:text-[#4a662e]'); ?>">
    <i class="fa-solid fa-compass w-5 text-center"></i>
    استكشف الكورسات
</a>
<?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/Admin/users/partials/sidebar.blade.php ENDPATH**/ ?>