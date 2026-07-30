<?php $__env->startSection('content'); ?>
    <div x-data="{
        activeTab: 3,
        showLoginModal: false,
        showSubscribeModal: false,
        selectedCourseId: null,
        enrolledCourseIds: <?php echo \Illuminate\Support\Js::from($userEnrolledCourseIds ?? [])->toHtml() ?>,
        isAuth: <?php echo json_encode(auth()->check(), 15, 512) ?>,
        handleCourseClick(courseId) {
            if (!this.isAuth) {
                this.showLoginModal = true;
            } else {
                if (this.enrolledCourseIds.includes(courseId)) {
                    window.location.href = `/user/courses/${courseId}`;
                } else {
                    this.selectedCourseId = courseId;
                    this.showSubscribeModal = true;
                }
            }
        }
    }" class="max-w-7xl mx-auto px-4 md:px-8 py-8">

        <!-- Tabs Navigation -->
        <div class="flex flex-col md:flex-row justify-center items-center gap-4 mb-12">
            <button @click="activeTab = 1"
                :class="activeTab === 1 ?
                    'bg-gradient-to-br from-gold-light to-gold text-text-primary shadow-[0_4px_20px_rgba(201,150,58,0.4)] scale-105' :
                    'bg-bg-card border border-glass-border text-text-secondary hover:text-text-primary'"
                class="w-full md:w-auto px-8 py-3 rounded-full font-bold transition-all duration-300">
                عن الشركة
            </button>
            <button @click="activeTab = 2"
                :class="activeTab === 2 ?
                    'bg-gradient-to-br from-gold-light to-gold text-text-primary shadow-[0_4px_20px_rgba(201,150,58,0.4)] scale-105' :
                    'bg-bg-card border border-glass-border text-text-secondary hover:text-text-primary'"
                class="w-full md:w-auto px-8 py-3 rounded-full font-bold transition-all duration-300">
                الكورسات المتاحة
            </button>
            <button @click="activeTab = 3"
                :class="activeTab === 3 ?
                    'bg-gradient-to-br from-gold-light to-gold text-text-primary shadow-[0_4px_20px_rgba(201,150,58,0.4)] scale-105' :
                    'bg-bg-card border border-glass-border text-text-secondary hover:text-text-primary'"
                class="w-full md:w-auto px-8 py-3 rounded-full font-bold transition-all duration-300">
                أعمال المتدربين
            </button>
        </div>

        <!-- Tab 1: About Company -->
        <div x-show="activeTab === 1" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;" class="py-8">

            <!-- Projects Section (Infinite Marquee) -->
            <div class="mb-24 pt-8">
                <div class="flex flex-col items-center text-center mb-12 relative px-4">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-5 py-1.5 rounded-full text-sm font-bold mb-5"
                        style="background-color: rgba(201,150,58,0.15); border: 1px solid rgba(201,150,58,0.3); color: #4a662e;">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #c9963a;"></span>
                        سابقة أعمالنا
                    </div>

                    <!-- Title -->
                    <h3 class="text-4xl md:text-5xl font-black text-text-primary leading-tight mb-6">
                        معرض <span style="color: #c9963a;">المشاريع</span>
                    </h3>

                    <!-- Description -->
                    <p style="color: #52525b; max-width: 800px; line-height: 1.8;"
                        class="text-lg md:text-xl font-medium mx-auto relative z-10">
                        نماذج من أعمالنا ومشاريعنا التي نفخر بتنفيذها بأعلى <strong style="color: #4a662e;">معايير
                            الجودة</strong> و<strong style="color: #4a662e;">الاحترافية</strong>.
                    </p>

                    <!-- Decorative Line -->
                    <div class="w-24 h-1.5 rounded-full mt-8 mx-auto"
                        style="background: linear-gradient(90deg, transparent, #c9963a, transparent); opacity: 0.8;"></div>
                </div>

                <?php
                    $projectImages = [];
                    $projectsPath = public_path('images/projects');
                    if (File::exists($projectsPath)) {
                        $files = File::files($projectsPath);
                        foreach ($files as $file) {
                            $projectImages[] = 'images/projects/' . $file->getFilename();
                        }
                        // Sort naturally so 1.jpg is followed by 2.jpg, not 10.jpg
                        natsort($projectImages);
                    }
                ?>

                <!-- Infinite Slider Container -->
                <div class="overflow-hidden w-full relative py-4" dir="ltr" x-data="{ lightboxOpen: false, lightboxImage: '' }">
                    <style>
                        @keyframes marqueeScrollLeftToRight {
                            0% {
                                transform: translateX(-50%);
                            }

                            100% {
                                transform: translateX(0%);
                            }
                        }

                        .animate-infinite-scroll {
                            animation: marqueeScrollLeftToRight 400s linear infinite;
                            display: flex;
                            width: max-content;
                        }

                        .animate-infinite-scroll:hover {
                            animation-play-state: paused;
                        }
                    </style>

                    <div class="animate-infinite-scroll gap-6 px-3">
                        <?php $__currentLoopData = $projectImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div @click="lightboxOpen = true; lightboxImage = '<?php echo e(asset($img)); ?>'"
                                class="w-72 h-80 flex-shrink-0 rounded-3xl overflow-hidden shadow-md border border-glass-border relative group bg-white p-2 cursor-pointer">
                                <img src="<?php echo e(asset($img)); ?>" loading="lazy" alt="Project Image"
                                    class="w-full h-full object-cover rounded-2xl group-hover:scale-105 transition-transform duration-700 ease-out" />
                                <div
                                    class="absolute inset-2 rounded-2xl bg-black/0 group-hover:bg-black/30 transition-colors duration-300 flex items-center justify-center">
                                    <i
                                        class="fa-solid fa-magnifying-glass-plus text-white text-4xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform scale-50 group-hover:scale-100"></i>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <!-- Duplicate set for seamless infinite scrolling -->
                        <?php $__currentLoopData = $projectImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div @click="lightboxOpen = true; lightboxImage = '<?php echo e(asset($img)); ?>'"
                                class="w-72 h-80 flex-shrink-0 rounded-3xl overflow-hidden shadow-md border border-glass-border relative group bg-white p-2 cursor-pointer">
                                <img src="<?php echo e(asset($img)); ?>" loading="lazy" alt="Project Image"
                                    class="w-full h-full object-cover rounded-2xl group-hover:scale-105 transition-transform duration-700 ease-out" />
                                <div
                                    class="absolute inset-2 rounded-2xl bg-black/0 group-hover:bg-black/30 transition-colors duration-300 flex items-center justify-center">
                                    <i
                                        class="fa-solid fa-magnifying-glass-plus text-white text-4xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform scale-50 group-hover:scale-100"></i>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <!-- Lightbox Modal -->
                    <div x-show="lightboxOpen" style="display: none;"
                        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm"
                        x-transition.opacity.duration.300ms>
                        <!-- Click outside to close (covers entire screen) -->
                        <div class="absolute inset-0 cursor-zoom-out" @click="lightboxOpen = false"></div>

                        <button @click="lightboxOpen = false"
                            class="absolute z-50 cursor-pointer transition-colors hover:text-white"
                            style="top: 2rem; right: 2rem; color: rgba(255,255,255,0.7); font-size: 2.5rem;">
                            <i class="fa-solid fa-xmark"></i>
                        </button>

                        <!-- The Image container -->
                        <div class="relative z-10 w-full h-full flex items-center justify-center"
                            style="pointer-events: none; padding: 12vh 10vw;">
                            <img :src="lightboxImage" class="object-contain rounded-xl"
                                style="pointer-events: auto; max-width: 100%; max-height: 100%; width: auto; height: auto; box-shadow: 0 20px 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);"
                                @click.stop />
                        </div>
                    </div>
                </div>
            </div>
            <!-- Hero & Vision Combined Section -->
            <div class="relative bg-bg-card border border-glass-border rounded-3xl overflow-hidden shadow-2xl mb-20">
                <!-- Decorative blurs -->
                <div
                    class="absolute -right-20 -top-20 w-72 h-72 bg-gold opacity-10 blur-3xl rounded-full pointer-events-none">
                </div>
                <div
                    class="absolute -left-20 -bottom-20 w-72 h-72 bg-green-700 opacity-10 blur-3xl rounded-full pointer-events-none">
                </div>


                <div class="relative z-10 flex flex-col lg:flex-row items-stretch">
                    <!-- Right Column (Hero Text) -->
                    <div
                        class="w-full lg:w-1/2 p-8 lg:p-12 xl:p-16 flex flex-col justify-center text-center lg:text-right border-b lg:border-b-0 lg:border-l border-glass-border">
                        <div class="mb-6">
                            <span
                                class="inline-block bg-glass-bg border border-glass-border text-text-secondary font-bold px-5 py-2 rounded-full text-sm">
                                الأفضل في الوطن العربي
                            </span>
                        </div>

                        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-text-primary mb-6 leading-tight">
                            المكتب الفني <br> <span class="text-gold">لأعمال اللآندسكيب</span>
                        </h2>

                        <p class="text-text-muted text-lg leading-relaxed mb-10 max-w-xl mx-auto lg:mx-0">
                            شركة لآندسكيب متخصصة في تقديم خدمات تدريبية للمهندسين والشركات وحلول فنية وتصميمات لآندسكيب
                            بأعلى معايير الجودة بهدف تطوير قطاع اللآندسكيب في مصر والوطن العربي.
                        </p>

                        <div>
                            <a href="https://wa.me/201229004186" target="_blank"
                                class="inline-flex items-center justify-center gap-3 bg-gold hover:bg-gold-light text-[#1a2310] px-10 py-4 rounded-full font-black text-lg transition-transform hover:-translate-y-1 shadow-[0_10px_20px_rgba(201,150,58,0.3)]">
                                تواصل مع خدمة العملاء
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Left Column (Vision & Leader Image) -->
                    <div
                        class="w-full lg:w-1/2 p-8 lg:p-12 xl:p-16 flex flex-col items-center justify-center bg-gradient-to-br from-transparent to-glass-bg/30">
                        <div class="relative mb-8 mt-4">
                            <!-- Decorative elements behind image -->
                            <div class="absolute inset-0 bg-green-700/20 rounded-full blur-2xl animate-pulse"></div>
                            <div
                                class="w-56 h-56 md:w-64 md:h-64 rounded-full border-4 border-green-700/30 shadow-[0_0_40px_rgba(74,102,46,0.2)] overflow-hidden bg-glass-bg relative z-10">
                                <img src="<?php echo e(asset('images/photo.jpg')); ?>" alt="Eng. Eslam Elshaer"
                                    class="w-full h-full object-cover hover:scale-110 transition-transform duration-700">
                            </div>
                        </div>

                        <div
                            class="text-center bg-glass-bg/60 backdrop-blur-md border border-glass-border rounded-2xl p-6 w-full max-w-md shadow-lg hover:border-gold/40 transition-colors">
                            <h3 class="text-2xl font-black text-text-primary mb-2">رؤية هندسية.. وإدارة محترفة</h3>
                            <h4 class="text-lg text-green-700 font-bold mb-3">بقيادة م. إسلام الشاعر</h4>
                            <p class="text-text-muted text-sm leading-relaxed">
                                نقدم حلولاً متكاملة في إدارة المكتب الفني، مدعومة بخبرة قيادية تضمن نجاح مشاريعكم.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Section -->
            <div class="mb-20 pt-16 md:pt-24">
                <div class="flex flex-col items-center text-center mb-16 relative px-4">


                    <!-- Title -->
                    <h3 class="text-4xl md:text-5xl font-black text-text-primary leading-tight mb-6">
                        الخدمات <span style="color: #c9963a;">الفنية</span>
                    </h3>

                    <!-- Description -->
                    <p style="color: #52525b; max-width: 800px; line-height: 1.8;"
                        class="text-lg md:text-xl font-medium mx-auto relative z-10">
                        شريكك الاستراتيجي في أعمال المكتب الفني. نضمن لك <strong style="color: #4a662e;">دقة في
                            التفاصيل</strong>، و<strong style="color: #4a662e;">احترافية في التخطيط</strong>، وحلولاً
                        مستدامة تتوافق مع أعلى المعايير الهندسية.
                    </p>

                    <!-- Decorative Line -->
                    <div class="w-24 h-1.5 rounded-full mt-10 mx-auto"
                        style="background: linear-gradient(90deg, transparent, #c9963a, transparent); opacity: 0.8;"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Service 1 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;"
                        class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                            style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-pen-ruler"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;"
                                class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-pen-ruler"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider"
                                dir="ltr">Design</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                تصميم أعمال اللآندسكيب وشبكات ري بأحدث البرامج والتقنيات ثنائية وثلاثية الأبعاد، مع إعداد
                                حسابات هيدروليكية دقيقة، للوصول إلى أفضل جودة تصميمية وتنفيذية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 2 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;"
                        class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                            style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-compass-drafting"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;"
                                class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-compass-drafting"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider"
                                dir="ltr">Shop Drawing</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                إعداد مخططات Shop Drawing عالية الدقة لأعمال اللآندسكيب وشبكات الري، جاهزة للاعتماد ومطابقة
                                لأعلى المعايير الهندسية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 3 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;"
                        class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                            style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;"
                                class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider"
                                dir="ltr">Pricing (BOQ)</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                نوفر خدمة إعداد وتسعير الـ BOQ الخاصة بأعمال اللآندسكيب وشبكات الري، مع تحليل بنود تفصيلي
                                ودقيق للمشاريع داخل مصر والسعودية.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Services Row 2 (Centered) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:w-2/3 mx-auto mt-8">
                    <!-- Service 4 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;"
                        class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                            style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;"
                                class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-headset"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider"
                                dir="ltr">Back Office</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                نوفر جميع خدمات المكتب الفني للشركات عن بُعد، بما يشمل الإعداد الفني، المراجعات الهندسية،
                                وتوفير الدعم الكامل لضمان سير العمل باحترافية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 5 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;"
                        class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12"
                            style="color: rgba(74,102,46,0.03); font-size: 14rem; line-height: 1;">
                            <i class="fa-solid fa-leaf"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;"
                                class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-leaf"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider"
                                dir="ltr">Environmental</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium max-w-2xl">
                                نقدم استشارات بيئية متخصصة وحلولاً مستدامة في مجال اللآندسكيب، بما يضمن تحقيق أعلى معايير
                                الجودة البيئية للمشاريع.
                            </p>
                        </div>
                    </div>
                </div>
            </div>



        </div>

        <!-- Tab 2: Available Courses -->
        <div x-show="activeTab === 2" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php $__empty_1 = true; $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div @click="handleCourseClick(<?php echo e($course->id); ?>)"
                        class="group cursor-pointer bg-bg-card border border-glass-border rounded-3xl overflow-hidden hover:-translate-y-2 hover:shadow-[0_10px_40px_rgba(201,150,58,0.15)] hover:border-gold/40 transition-all duration-500 flex flex-col">
                        <div class="relative h-56 overflow-hidden bg-glass-bg">
                            <?php if($course->image_path): ?>
                                <img src="<?php echo e(Storage::url($course->image_path)); ?>" alt="<?php echo e($course->title); ?>"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                            <?php else: ?>
                                <div
                                    class="w-full h-full flex items-center justify-center text-gold text-5xl opacity-50 group-hover:scale-110 transition-transform duration-700">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </div>
                            <?php endif; ?>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-bg-primary via-transparent to-transparent opacity-80">
                            </div>
                            <div
                                class="absolute bottom-4 right-4 bg-glass-bg backdrop-blur-md border border-glass-border px-3 py-1 rounded-full text-xs font-bold text-gold">
                                <?php echo e($course->price > 0 ? $course->price . ' ج.م' : 'مجاني'); ?>

                            </div>
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-xl font-bold text-text-primary mb-2 group-hover:text-gold transition-colors">
                                <?php echo e($course->title); ?></h3>
                            <p class="text-text-muted text-sm line-clamp-3 mb-4 flex-1"><?php echo e($course->description); ?></p>

                            <div class="flex items-center justify-between border-t border-glass-border pt-4 mt-auto">
                                <span class="text-xs font-bold px-3 py-1.5 rounded-full"
                                    :class="enrolledCourseIds.includes(<?php echo e($course->id); ?>) ?
                                        'bg-green-500/10 text-green-500 border border-green-500/20' :
                                        'bg-glass-bg text-text-secondary border border-glass-border'">
                                    <span
                                        x-text="enrolledCourseIds.includes(<?php echo e($course->id); ?>) ? 'مشترك ومفعل' : 'متاح للاشتراك'"></span>
                                </span>
                                <div
                                    class="w-8 h-8 rounded-full bg-glass-bg border border-glass-border flex items-center justify-center group-hover:bg-gold group-hover:text-bg-primary transition-colors">
                                    <i class="fa-solid fa-arrow-left"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div
                        class="col-span-full py-12 text-center text-text-muted border border-dashed border-glass-border rounded-3xl">
                        لا توجد كورسات متاحة حالياً.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab 3: Trainee Projects (Old Index Content) -->
        <div x-show="activeTab === 3" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;">
            <!-- Hero / Header Section -->
            <section class="flex flex-col items-center text-center pt-8 pb-8 px-6">
                <div
                    class="inline-flex items-center gap-2 bg-gold-pale border border-gold-light/30 rounded-full px-5 py-1.5 text-xs font-bold text-[#4a662e] mb-4 tracking-wide uppercase">
                    <span class="w-1.5 h-1.5 bg-gold rounded-full animate-pulse"></span>
                    لوحة الشرف
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-text-primary leading-tight mb-4">
                    أعمال <span class="bg-gradient-to-r from-gold-light to-gold bg-clip-text text-transparent">متدربينا
                        المتميزين</span>
                </h2>
                <div class="w-14 h-1 rounded-full bg-gradient-to-r from-transparent via-gold to-transparent"></div>

                <?php if($setting->master_drive_link): ?>
                    <div class="mt-8">
                        <a href="<?php echo e($setting->master_drive_link); ?>" target="_blank"
                            class="inline-flex items-center gap-2 px-7 py-3 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-full hover:-translate-y-1 hover:shadow-[0_6px_20px_rgba(201,150,58,0.4)] transition-all duration-300">
                            <i class="fa-brands fa-google-drive"></i>
                            رابط أعمال المتدربين (Google Drive)
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Carousel Section (Custom 3D Logic retained) -->
            <style>
                .carousel-container {
                    position: relative;
                    height: 420px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    overflow: hidden;
                    cursor: grab;
                    user-select: none;
                }

                .carousel-card {
                    position: absolute;
                    width: 340px;
                    background: var(--color-bg-card);
                    border: 1px solid var(--color-glass-border);
                    border-radius: 28px;
                    padding: 14px 14px 16px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    transition: all 0.52s cubic-bezier(0.23, 1, 0.32, 1);
                    cursor: pointer;
                    transform: scale(0.55);
                    opacity: 0;
                    filter: blur(8px);
                    z-index: 1;
                    backdrop-filter: blur(12px);
                    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.45);
                    overflow: hidden;
                }

                .carousel-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    border-radius: inherit;
                    background: linear-gradient(120deg, transparent 25%, rgba(255, 255, 255, var(--glare-opacity, 0)) var(--glare-pos, 50%), transparent 75%);
                    pointer-events: none;
                    z-index: 10;
                }

                .carousel-card.active {
                    transform: scale(1.05);
                    opacity: 1;
                    filter: blur(0);
                    z-index: 3;
                    border-color: rgba(201, 150, 58, 0.35);
                    box-shadow: 0 0 0 1px rgba(201, 150, 58, 0.18), 0 24px 64px rgba(0, 0, 0, 0.55), 0 0 50px rgba(201, 150, 58, 0.10);
                }

                .carousel-card.prev {
                    transform: translateX(-230px) scale(0.78);
                    opacity: 0.45;
                    filter: blur(3.5px);
                    z-index: 2;
                }

                .carousel-card.next {
                    transform: translateX(230px) scale(0.78);
                    opacity: 0.45;
                    filter: blur(3.5px);
                    z-index: 2;
                }

                .carousel-container.dragging .carousel-card {
                    transition: none;
                }

                .carousel-img {
                    width: 100%;
                    height: 220px;
                    object-fit: cover;
                    border-radius: 16px;
                    transition: transform 0.5s;
                }

                .carousel-card.active .carousel-img {
                    transform: scale(1.02);
                }

                .facebook-logo {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 14px;
                    border-radius: 100px;
                    background: rgba(24, 119, 242, 0.15);
                    border: 1px solid rgba(24, 119, 242, 0.4);
                    color: #1877F2;
                    font-size: 12.5px;
                    font-weight: 700;
                    opacity: 0;
                    transform: translateY(8px);
                    transition: all 0.4s;
                }

                .carousel-card.active .facebook-logo {
                    opacity: 1;
                    transform: translateY(0);
                }

                .facebook-logo:hover {
                    background: #1877F2;
                    color: #ffffff;
                    border-color: #1877F2;
                }

                @media (max-width: 680px) {
                    .carousel-container {
                        height: 380px;
                    }

                    .carousel-card {
                        width: 290px;
                    }

                    .carousel-card.prev {
                        transform: translateX(-150px) scale(0.78);
                    }

                    .carousel-card.next {
                        transform: translateX(150px) scale(0.78);
                    }
                }

                @media (max-width: 480px) {
                    .carousel-card {
                        width: 250px;
                    }

                    .carousel-card.prev {
                        transform: translateX(-125px) scale(0.78);
                    }

                    .carousel-card.next {
                        transform: translateX(125px) scale(0.78);
                    }
                }
            </style>

            <div class="py-5">
                <div class="carousel-container" id="carouselContainer">
                    <button
                        class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full border border-glass-border bg-glass-bg backdrop-blur-md text-[#4a662e] z-10 flex items-center justify-center hover:bg-gold-pale hover:border-gold/50 hover:scale-110 transition-all carousel-btn--prev">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button
                        class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full border border-glass-border bg-glass-bg backdrop-blur-md text-[#4a662e] z-10 flex items-center justify-center hover:bg-gold-pale hover:border-gold/50 hover:scale-110 transition-all carousel-btn--next">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                </div>
                <div class="flex justify-center items-center gap-2 py-6 carousel-dots" id="carouselDots"></div>
            </div>

            <!-- Software Programs Section -->
            <section class="flex flex-col items-center text-center pt-8 pb-4 px-6">
                <div
                    class="inline-flex items-center gap-2 bg-gold-pale border border-gold-light/30 rounded-full px-5 py-1.5 text-xs font-bold text-[#4a662e] mb-4 tracking-wide uppercase">
                    <span class="w-1.5 h-1.5 bg-gold rounded-full animate-pulse"></span>
                    البرامج التدريبية
                </div>
                <h2 class="text-3xl md:text-5xl font-black text-text-primary leading-tight mb-4">
                    أعمال <span
                        class="bg-gradient-to-r from-gold-light to-gold bg-clip-text text-transparent">المتدربين</span>
                </h2>
                <div class="w-14 h-1 rounded-full bg-gradient-to-r from-transparent via-gold to-transparent mb-12"></div>

                <div class="w-full max-w-5xl mx-auto flex flex-col gap-6">
                    <!-- Row 1 -->
                    <div class="flex flex-wrap justify-center gap-6">
                        <!-- Card 1 -->
                        <a href="<?php echo e($setting->sketchup_link ?? '#'); ?>" target="_blank"
                            class="block group relative w-[300px] h-[220px] rounded-[22px] overflow-hidden bg-bg-card border border-glass-border backdrop-blur-md shadow-[0_4px_28px_rgba(0,0,0,0.35)] hover:-translate-y-3 hover:border-gold/30 hover:shadow-[0_24px_56px_rgba(0,0,0,0.55)] transition-all duration-500">
                            <div class="absolute inset-0 bg-white/30 h-[158px] flex items-center justify-center p-4">
                                <img src="<?php echo e(asset('images/Lumun & SketchUP.png')); ?>"
                                    class="max-h-[116px] object-contain drop-shadow-md group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500"
                                    alt="Sketchup">
                            </div>
                            <div
                                class="absolute top-[158px] left-0 w-full h-px bg-gradient-to-r from-transparent via-glass-border to-transparent group-hover:via-gold/40 transition-colors">
                            </div>
                            <h2
                                class="absolute bottom-0 inset-x-0 h-[62px] flex items-center justify-center font-bold text-text-secondary group-hover:text-[#4a662e] transition-colors text-sm tracking-wide">
                                Sketchup & Lumion</h2>
                        </a>

                        <!-- Card 2 -->
                        <a href="<?php echo e($setting->max_link ?? '#'); ?>" target="_blank"
                            class="block group relative w-[300px] h-[220px] rounded-[22px] overflow-hidden bg-bg-card border border-glass-border backdrop-blur-md shadow-[0_4px_28px_rgba(0,0,0,0.35)] hover:-translate-y-3 hover:border-gold/30 hover:shadow-[0_24px_56px_rgba(0,0,0,0.55)] transition-all duration-500">
                            <div class="absolute inset-0 bg-white/30 h-[158px] flex items-center justify-center p-4">
                                <img src="<?php echo e(asset('images/3DMAXandCorona.png')); ?>"
                                    class="max-h-[116px] object-contain drop-shadow-md group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500"
                                    alt="3Ds Max">
                            </div>
                            <div
                                class="absolute top-[158px] left-0 w-full h-px bg-gradient-to-r from-transparent via-glass-border to-transparent group-hover:via-gold/40 transition-colors">
                            </div>
                            <h2
                                class="absolute bottom-0 inset-x-0 h-[62px] flex items-center justify-center font-bold text-text-secondary group-hover:text-[#4a662e] transition-colors text-sm tracking-wide">
                                3Ds Max & Corona</h2>
                        </a>

                        <!-- Card 3 -->
                        <a href="<?php echo e($setting->autocad_link ?? '#'); ?>" target="_blank"
                            class="block group relative w-[300px] h-[220px] rounded-[22px] overflow-hidden bg-bg-card border border-glass-border backdrop-blur-md shadow-[0_4px_28px_rgba(0,0,0,0.35)] hover:-translate-y-3 hover:border-gold/30 hover:shadow-[0_24px_56px_rgba(0,0,0,0.55)] transition-all duration-500">
                            <div class="absolute inset-0 bg-white/30 h-[158px] flex items-center justify-center p-4">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/6e/AutoCad_new_logo.svg/960px-AutoCad_new_logo.svg.png"
                                    class="max-h-[116px] object-contain drop-shadow-md group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500"
                                    alt="Autocad">
                            </div>
                            <div
                                class="absolute top-[158px] left-0 w-full h-px bg-gradient-to-r from-transparent via-glass-border to-transparent group-hover:via-gold/40 transition-colors">
                            </div>
                            <h2
                                class="absolute bottom-0 inset-x-0 h-[62px] flex items-center justify-center font-bold text-text-secondary group-hover:text-[#4a662e] transition-colors text-sm tracking-wide">
                                Autocad</h2>
                        </a>
                    </div>

                    <!-- Row 2 -->
                    <div class="flex flex-wrap justify-center gap-6">
                        <!-- Card 4 -->
                        <a href="<?php echo e($setting->manual_link ?? '#'); ?>" target="_blank"
                            class="block group relative w-[300px] h-[220px] rounded-[22px] overflow-hidden bg-bg-card border border-glass-border backdrop-blur-md shadow-[0_4px_28px_rgba(0,0,0,0.35)] hover:-translate-y-3 hover:border-gold/30 hover:shadow-[0_24px_56px_rgba(0,0,0,0.55)] transition-all duration-500">
                            <div class="absolute inset-0 bg-white/30 h-[158px] flex items-center justify-center p-4">
                                <img src="<?php echo e(asset('images/manual-artistry.png')); ?>"
                                    class="max-h-[116px] object-contain drop-shadow-md group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500"
                                    alt="Manual Sketch">
                            </div>
                            <div
                                class="absolute top-[158px] left-0 w-full h-px bg-gradient-to-r from-transparent via-glass-border to-transparent group-hover:via-gold/40 transition-colors">
                            </div>
                            <h2
                                class="absolute bottom-0 inset-x-0 h-[62px] flex items-center justify-center font-bold text-text-secondary group-hover:text-[#4a662e] transition-colors text-sm tracking-wide">
                                Manual Sketch</h2>
                        </a>

                        <!-- Card 5 -->
                        <a href="<?php echo e($setting->landscape_link ?? '#'); ?>" target="_blank"
                            class="block group relative w-[300px] h-[220px] rounded-[22px] overflow-hidden bg-bg-card border border-glass-border backdrop-blur-md shadow-[0_4px_28px_rgba(0,0,0,0.35)] hover:-translate-y-3 hover:border-gold/30 hover:shadow-[0_24px_56px_rgba(0,0,0,0.55)] transition-all duration-500">
                            <div class="absolute inset-0 bg-white/30 h-[158px] flex items-center justify-center p-4">
                                <img src="<?php echo e(asset('images/RealTime.png')); ?>"
                                    class="max-h-[116px] object-contain drop-shadow-md group-hover:scale-110 group-hover:-translate-y-1 transition-transform duration-500"
                                    alt="Landscape">
                            </div>
                            <div
                                class="absolute top-[158px] left-0 w-full h-px bg-gradient-to-r from-transparent via-glass-border to-transparent group-hover:via-gold/40 transition-colors">
                            </div>
                            <h2
                                class="absolute bottom-0 inset-x-0 h-[62px] flex items-center justify-center font-bold text-text-secondary group-hover:text-[#4a662e] transition-colors text-sm tracking-wide">
                                Real Time Landscape</h2>
                        </a>
                    </div>
                </div>
            </section>
        </div>

        <!-- Modals -->

        <!-- Login Modal -->
        <div x-show="showLoginModal" style="display: none;"
            class="fixed inset-0 z-[100] flex items-center justify-center px-4">
            <div x-show="showLoginModal" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                @click="showLoginModal = false"></div>
            <div x-show="showLoginModal" x-transition.scale.80
                class="relative bg-bg-secondary border border-glass-border rounded-3xl p-8 max-w-sm w-full text-center shadow-[0_20px_60px_rgba(0,0,0,0.5)] z-10">
                <button @click="showLoginModal = false"
                    class="absolute top-4 left-4 text-text-muted hover:text-text-primary transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
                <div
                    class="w-16 h-16 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-500 flex items-center justify-center text-3xl mx-auto mb-6">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </div>
                <h3 class="text-2xl font-bold text-text-primary mb-2">تسجيل الدخول مطلوب</h3>
                <p class="text-text-muted mb-6">يجب عليك تسجيل الدخول أولاً للتمكن من الاشتراك ومشاهدة الكورسات.</p>
                <a href="<?php echo e(route('login')); ?>"
                    class="block w-full py-3 bg-gradient-to-br from-gold-light to-gold text-text-primary font-bold rounded-full hover:shadow-[0_4px_15px_rgba(201,150,58,0.3)] hover:-translate-y-1 transition-all">
                    تسجيل الدخول الآن
                </a>
            </div>
        </div>

        <!-- Subscribe / Whatsapp Modal -->
        <div x-show="showSubscribeModal" style="display: none;"
            class="fixed inset-0 z-[100] flex items-center justify-center px-4">
            <div x-show="showSubscribeModal" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                @click="showSubscribeModal = false"></div>
            <div x-show="showSubscribeModal" x-transition.scale.80
                class="relative bg-bg-secondary border border-glass-border rounded-3xl p-8 max-w-sm w-full text-center shadow-[0_20px_60px_rgba(0,0,0,0.5)] z-10">
                <button @click="showSubscribeModal = false"
                    class="absolute top-4 left-4 text-text-muted hover:text-text-primary transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
                <div
                    class="w-16 h-16 rounded-full bg-green-500/10 border border-green-500/20 text-green-500 flex items-center justify-center text-3xl mx-auto mb-6">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <h3 class="text-2xl font-bold text-text-primary mb-2">تفعيل الكورس</h3>
                <p class="text-text-muted mb-6">هذا الكورس غير مفعل في حسابك. يرجى التواصل معنا عبر الواتساب لتفعيل اشتراكك
                    وتأكيده.</p>
                <a :href="'https://wa.me/201229004186?text=مرحباً، أريد الاشتراك وتفعيل الكورس رقم: ' + selectedCourseId"
                    target="_blank"
                    class="block w-full py-3 bg-[#25D366] text-white font-bold rounded-full hover:shadow-[0_4px_15px_rgba(37,211,102,0.3)] hover:-translate-y-1 transition-all">
                    تواصل عبر الواتساب
                </a>
            </div>
        </div>

    </div>

    <!-- Carousel Script for Tab 3 -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const projectsData = <?php echo json_encode($projects, 15, 512) ?>;
            const carouselContainer = document.getElementById('carouselContainer');
            const prevBtn = document.querySelector('.carousel-btn--prev');
            const nextBtn = document.querySelector('.carousel-btn--next');
            const dotsContainer = document.getElementById('carouselDots');

            let cards = [];
            let activeIndex = 0;
            let autoPlayInterval;

            // Handle Responsive offset
            let OFFSET = window.innerWidth <= 680 ? (window.innerWidth <= 480 ? 125 : 150) : 230;
            window.addEventListener('resize', () => {
                OFFSET = window.innerWidth <= 680 ? (window.innerWidth <= 480 ? 125 : 150) : 230;
            });

            const loadDataToUI = () => {
                if (projectsData && projectsData.length > 0) {
                    projectsData.forEach((p, index) => {
                        const card = document.createElement('div');
                        card.classList.add('carousel-card');
                        card.innerHTML = `
                        <a href="${p.drive_link}" target="_blank" class="block w-full rounded-2xl overflow-hidden">
                            <img src="/storage/${p.image_path}" alt="مشروع ${index + 1}" class="carousel-img">
                        </a>
                        <div class="w-full flex items-center justify-between mt-3 px-1">
                            <span class="text-xs font-bold text-text-muted group-[.active]:text-[#4a662e] transition-colors">مشروع ${index + 1}</span>
                            <a href="${p.facebook_link}" target="_blank" class="facebook-logo group-[.active]:opacity-100 group-[.active]:translate-y-0">
                                <i class="fa-brands fa-facebook"></i> تابعنا
                            </a>
                        </div>
                    `;
                        carouselContainer.insertBefore(card, prevBtn);
                    });
                }

                cards = document.querySelectorAll('.carousel-card');

                if (cards.length > 0) {
                    cards.forEach(card => {
                        const links = card.querySelectorAll('a');
                        links.forEach(link => {
                            link.addEventListener('click', (e) => {
                                if (isDragged || card.classList.contains('prev') || card
                                    .classList.contains('next')) {
                                    e.preventDefault();
                                }
                            });
                        });

                        card.addEventListener('click', () => {
                            if (isDragged) return;
                            if (card.classList.contains('prev')) {
                                goToPrev();
                                startAutoPlay();
                            } else if (card.classList.contains('next')) {
                                goToNext();
                                startAutoPlay();
                            }
                        });
                    });
                }
            };

            const updateCarousel = () => {
                if (!cards.length) return;
                cards.forEach((card, i) => {
                    card.classList.remove('active', 'prev', 'next', 'group');
                    const prevIndex = (activeIndex - 1 + cards.length) % cards.length;
                    const nextIndex = (activeIndex + 1) % cards.length;

                    if (i === activeIndex) {
                        card.classList.add('active', 'group');
                    } else if (i === prevIndex) card.classList.add('prev');
                    else if (i === nextIndex) card.classList.add('next');
                });
                updateDots();
            };

            const updateDots = () => {
                document.querySelectorAll('.dot-btn').forEach((dot, i) => {
                    if (i === activeIndex) {
                        dot.classList.add('w-8', 'bg-gold', 'shadow-[0_0_12px_rgba(201,150,58,0.55)]');
                        dot.classList.remove('w-2', 'bg-text-muted');
                    } else {
                        dot.classList.remove('w-8', 'bg-gold',
                            'shadow-[0_0_12px_rgba(201,150,58,0.55)]');
                        dot.classList.add('w-2', 'bg-text-muted');
                    }
                });
            };

            const goToNext = () => {
                if (!cards.length) return;
                activeIndex = (activeIndex + 1) % cards.length;
                updateCarousel();
            };
            const goToPrev = () => {
                if (!cards.length) return;
                activeIndex = (activeIndex - 1 + cards.length) % cards.length;
                updateCarousel();
            };

            const startAutoPlay = () => {
                if (!cards.length) return;
                clearInterval(autoPlayInterval);
                autoPlayInterval = setInterval(goToNext, 1000);
            };

            nextBtn.addEventListener('click', () => {
                goToNext();
                startAutoPlay();
            });
            prevBtn.addEventListener('click', () => {
                goToPrev();
                startAutoPlay();
            });
            carouselContainer.addEventListener('mouseenter', () => clearInterval(autoPlayInterval));
            carouselContainer.addEventListener('mouseleave', () => {
                if (!isDragging) startAutoPlay();
            });

            let isDragging = false;
            let isDragged = false;
            let startPos = 0;

            const handleDragStart = (e) => {
                if (!cards.length) return;
                isDragging = true;
                isDragged = false;
                startPos = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
                clearInterval(autoPlayInterval);
                carouselContainer.style.cursor = 'grabbing';
                carouselContainer.classList.add('dragging');
            };

            const handleDragMove = (e) => {
                if (!isDragging || !cards.length) return;
                const currentPos = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
                const movedBy = currentPos - startPos;
                if (Math.abs(movedBy) > 10) isDragged = true;

                const dragRatio = Math.min(Math.max(movedBy / OFFSET, -1), 1);
                const clampedMove = dragRatio * OFFSET;

                cards.forEach(card => {
                    if (card.classList.contains('active')) {
                        const scale = 1.05 - Math.abs(dragRatio) * 0.27;
                        const opacity = 1 - Math.abs(dragRatio) * 0.3;
                        const blur = Math.abs(dragRatio) * 4;
                        const rotate = dragRatio * -25;
                        const shadowX = rotate * -1;
                        card.style.transform =
                            `translateX(${clampedMove}px) scale(${scale}) rotateY(${rotate}deg)`;
                        card.style.opacity = opacity;
                        card.style.filter = `blur(${blur}px)`;
                        card.style.boxShadow =
                            `${shadowX}px 15px ${10 + scale * 10}px rgba(0,0,0,${scale * 0.18})`;
                    } else if (card.classList.contains('prev')) {
                        const scale = 0.78 + Math.max(0, dragRatio) * 0.27;
                        const opacity = 0.45 + Math.max(0, dragRatio) * 0.55;
                        const blur = 3.5 - Math.max(0, dragRatio) * 3.5;
                        const rotate = 25 - Math.max(0, dragRatio) * 25;
                        const shadowX = rotate * -1;
                        card.style.transform =
                            `translateX(${-OFFSET + clampedMove}px) scale(${scale}) rotateY(${rotate}deg)`;
                        card.style.opacity = opacity;
                        card.style.filter = `blur(${blur}px)`;
                        card.style.boxShadow =
                            `${shadowX}px 15px ${10 + scale * 10}px rgba(0,0,0,${scale * 0.18})`;
                    } else if (card.classList.contains('next')) {
                        const scale = 0.78 + Math.max(0, -dragRatio) * 0.27;
                        const opacity = 0.45 + Math.max(0, -dragRatio) * 0.55;
                        const blur = 3.5 - Math.max(0, -dragRatio) * 3.5;
                        const rotate = -25 + Math.max(0, -dragRatio) * 25;
                        const shadowX = rotate * -1;
                        card.style.transform =
                            `translateX(${OFFSET + clampedMove}px) scale(${scale}) rotateY(${rotate}deg)`;
                        card.style.opacity = opacity;
                        card.style.filter = `blur(${blur}px)`;
                        card.style.boxShadow =
                            `${shadowX}px 15px ${10 + scale * 10}px rgba(0,0,0,${scale * 0.18})`;
                    }
                });
            };

            const handleDragEnd = (e) => {
                if (!isDragging || !cards.length) return;
                isDragging = false;
                carouselContainer.style.cursor = 'grab';
                carouselContainer.classList.remove('dragging');

                cards.forEach(card => {
                    card.style.transform = '';
                    card.style.opacity = '';
                    card.style.filter = '';
                    card.style.boxShadow = '';
                });

                const endPos = e.type.includes('mouse') ? e.clientX : e.changedTouches[0].clientX;
                const movedBy = endPos - startPos;
                const threshold = 55;

                if (movedBy < -threshold) goToNext();
                else if (movedBy > threshold) goToPrev();

                startAutoPlay();
            };

            carouselContainer.addEventListener('touchstart', handleDragStart, {
                passive: true
            });
            carouselContainer.addEventListener('touchmove', handleDragMove, {
                passive: true
            });
            carouselContainer.addEventListener('touchend', handleDragEnd);
            carouselContainer.addEventListener('mousedown', handleDragStart);
            carouselContainer.addEventListener('mousemove', handleDragMove);
            window.addEventListener('mouseup', handleDragEnd);
            carouselContainer.addEventListener('dragstart', (e) => e.preventDefault());

            const buildDots = () => {
                if (!cards.length) return;
                cards.forEach((_, i) => {
                    const dot = document.createElement('button');
                    dot.classList.add('dot-btn', 'h-2', 'rounded-full', 'transition-all',
                        'duration-300', 'flex-shrink-0');
                    dot.addEventListener('click', () => {
                        activeIndex = i;
                        updateCarousel();
                        startAutoPlay();
                    });
                    dotsContainer.appendChild(dot);
                });
            };

            const init = () => {
                loadDataToUI();
                buildDots();
                updateCarousel();
                startAutoPlay();
            };
            init();
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/index.blade.php ENDPATH**/ ?>