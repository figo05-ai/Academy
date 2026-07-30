<?php $__env->startSection('title', 'إنشاء حساب جديد'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex items-center justify-center min-h-[calc(100vh-250px)] px-4 py-12">
    <div class="w-full max-w-md bg-bg-card border border-glass-border rounded-[22px] p-8 md:p-10 shadow-[0_8px_30px_rgba(0,0,0,0.3)] backdrop-blur-md relative overflow-hidden group">
        <!-- Decoration -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-gold-light/10 rounded-full blur-3xl pointer-events-none group-hover:bg-gold-light/20 transition-all duration-700"></div>
        <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-blue-accent/10 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-accent/20 transition-all duration-700"></div>

        <h2 class="text-center text-2xl font-bold text-[#4a662e] mb-8 relative z-10">
            <i class="fa-solid fa-user-plus ml-2"></i>إنشاء حساب جديد
        </h2>

        <form action="<?php echo e(route('register')); ?>" method="POST" class="relative z-10 space-y-5">
            <?php echo csrf_field(); ?>

            <div class="space-y-2">
                <label for="name" class="block text-sm font-semibold text-text-secondary">الاسم بالكامل:</label>
                <input type="text" id="name" name="name" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="أدخل اسمك" value="<?php echo e(old('name')); ?>" required>
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <span class="block text-sm font-semibold text-red-500 mt-1"><?php echo e($message); ?></span>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="space-y-2">
                <label for="email" class="block text-sm font-semibold text-text-secondary">البريد الإلكتروني:</label>
                <input type="email" id="email" name="email" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="user@example.com" value="<?php echo e(old('email')); ?>" required>
                <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <span class="block text-sm font-semibold text-red-500 mt-1"><?php echo e($message); ?></span>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="space-y-2">
                <label for="password" class="block text-sm font-semibold text-text-secondary">كلمة المرور:</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="••••••••" required>
                <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <span class="block text-sm font-semibold text-red-500 mt-1"><?php echo e($message); ?></span>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <div class="space-y-2">
                <label for="password_confirmation" class="block text-sm font-semibold text-text-secondary">تأكيد كلمة المرور:</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="w-full px-4 py-3.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-all duration-300 placeholder:text-text-muted/50" placeholder="••••••••" required>
            </div>

            <button type="submit" class="w-full py-3.5 mt-4 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black text-lg rounded-xl shadow-lg hover:-translate-y-1 hover:shadow-[0_6px_20px_rgba(201,150,58,0.3)] transition-all duration-300">
                <i class="fa-solid fa-check ml-2"></i> تسجيل حساب
            </button>
        </form>

        <div class="text-center mt-6 text-sm text-text-secondary relative z-10">
            لديك حساب بالفعل؟ <a href="<?php echo e(route('login')); ?>" class="text-[#4a662e] font-bold hover:text-[#3d5425] transition-colors">تسجيل الدخول</a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/Login/register.blade.php ENDPATH**/ ?>