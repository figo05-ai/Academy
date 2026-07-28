import re

file_path = "/mnt/Felo/Courses/learing/PHP/Laravel/Gorge/resources/views/index.blade.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

prefix, rest = content.split("<!-- Services Section -->", 1)
services_and_projects, suffix = rest.split("<!-- Tab 2: Available Courses -->", 1)

new_middle = """<!-- Projects Section -->
            <div class="mb-24 pt-16 md:pt-24">
                <div class="flex flex-col items-center text-center mb-16 relative px-4">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-5 py-1.5 rounded-full text-sm font-bold mb-5" style="background-color: rgba(201,150,58,0.15); border: 1px solid rgba(201,150,58,0.3); color: #4a662e;">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #c9963a;"></span>
                        سابقة أعمالنا
                    </div>
                    
                    <!-- Title -->
                    <h3 class="text-4xl md:text-5xl font-black text-text-primary leading-tight mb-6">
                        معرض <span style="color: #c9963a;">المشاريع</span>
                    </h3>
                    
                    <!-- Description -->
                    <p style="color: #52525b; max-width: 800px; line-height: 1.8;" class="text-lg md:text-xl font-medium mx-auto relative z-10">
                        نماذج من أعمالنا ومشاريعنا التي نفخر بتنفيذها بأعلى <strong style="color: #4a662e;">معايير الجودة</strong> و<strong style="color: #4a662e;">الاحترافية</strong>.
                    </p>
                    
                    <!-- Decorative Line -->
                    <div class="w-24 h-1.5 rounded-full mt-10 mx-auto" style="background: linear-gradient(90deg, transparent, #c9963a, transparent); opacity: 0.8;"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <!-- Project 1 Placeholder -->
                    <div class="group relative h-72 rounded-3xl overflow-hidden bg-glass-bg border border-glass-border cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 flex items-center justify-center text-text-muted/20 text-6xl group-hover:scale-125 transition-transform duration-700">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-lg mb-2">مشروع لآندسكيب - الرياض</h4>
                            <p class="text-gray-300 text-xs">تصميم وتخطيط متكامل للمساحات الخارجية</p>
                        </div>
                    </div>

                    <!-- Project 2 Placeholder -->
                    <div class="group relative h-72 rounded-3xl overflow-hidden bg-glass-bg border border-glass-border cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 flex items-center justify-center text-text-muted/20 text-6xl group-hover:scale-125 transition-transform duration-700">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-lg mb-2">حديقة عامة - القاهرة</h4>
                            <p class="text-gray-300 text-xs">مخططات Shop Drawing وحسابات هيدروليكية</p>
                        </div>
                    </div>

                    <!-- Project 3 Placeholder -->
                    <div class="group relative h-72 rounded-3xl overflow-hidden bg-glass-bg border border-glass-border cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 flex items-center justify-center text-text-muted/20 text-6xl group-hover:scale-125 transition-transform duration-700">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-lg mb-2">فيلا سكنية - جدة</h4>
                            <p class="text-gray-300 text-xs">تصميم شبكات الري وتسعير BOQ</p>
                        </div>
                    </div>

                    <!-- Project 4 Placeholder -->
                    <div class="group relative h-72 rounded-3xl overflow-hidden bg-glass-bg border border-glass-border cursor-pointer shadow-lg hover:shadow-2xl transition-all duration-300">
                        <div class="absolute inset-0 flex items-center justify-center text-text-muted/20 text-6xl group-hover:scale-125 transition-transform duration-700">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-lg mb-2">منتجع سياحي</h4>
                            <p class="text-gray-300 text-xs">استشارات بيئية وحلول مستدامة</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Section -->
            <div class="mb-20 pt-16 md:pt-24">
                <div class="flex flex-col items-center text-center mb-16 relative px-4">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 px-5 py-1.5 rounded-full text-sm font-bold mb-5" style="background-color: rgba(201,150,58,0.15); border: 1px solid rgba(201,150,58,0.3); color: #4a662e;">
                        <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #c9963a;"></span>
                        التميز الهندسي
                    </div>
                    
                    <!-- Title -->
                    <h3 class="text-4xl md:text-5xl font-black text-text-primary leading-tight mb-6">
                        الخدمات <span style="color: #c9963a;">الفنية</span>
                    </h3>
                    
                    <!-- Description -->
                    <p style="color: #52525b; max-width: 800px; line-height: 1.8;" class="text-lg md:text-xl font-medium mx-auto relative z-10">
                        شريكك الاستراتيجي في أعمال المكتب الفني. نضمن لك <strong style="color: #4a662e;">دقة في التفاصيل</strong>، و<strong style="color: #4a662e;">احترافية في التخطيط</strong>، وحلولاً مستدامة تتوافق مع أعلى المعايير الهندسية.
                    </p>
                    
                    <!-- Decorative Line -->
                    <div class="w-24 h-1.5 rounded-full mt-10 mx-auto" style="background: linear-gradient(90deg, transparent, #c9963a, transparent); opacity: 0.8;"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Service 1 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;" class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12" style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-pen-ruler"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-pen-ruler"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider" dir="ltr">Design</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                تصميم أعمال اللآندسكيب وشبكات ري بأحدث البرامج والتقنيات ثنائية وثلاثية الأبعاد، مع إعداد حسابات هيدروليكية دقيقة، للوصول إلى أفضل جودة تصميمية وتنفيذية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 2 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;" class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12" style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-compass-drafting"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-compass-drafting"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider" dir="ltr">Shop Drawing</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                إعداد مخططات Shop Drawing عالية الدقة لأعمال اللآندسكيب وشبكات الري، جاهزة للاعتماد ومطابقة لأعلى المعايير الهندسية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 3 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;" class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12" style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider" dir="ltr">Pricing (BOQ)</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                نوفر خدمة إعداد وتسعير الـ BOQ الخاصة بأعمال اللآندسكيب وشبكات الري، مع تحليل بنود تفصيلي ودقيق للمشاريع داخل مصر والسعودية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 4 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;" class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12" style="color: rgba(74,102,46,0.03); font-size: 10rem; line-height: 1;">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-headset"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider" dir="ltr">Back Office</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium">
                                نوفر جميع خدمات المكتب الفني للشركات عن بُعد، بما يشمل الإعداد الفني، المراجعات الهندسية، وتوفير الدعم الكامل لضمان سير العمل باحترافية.
                            </p>
                        </div>
                    </div>

                    <!-- Service 5 -->
                    <div style="background-color: #ffffff; box-shadow: 0 10px 40px rgba(74,102,46,0.08); border: 1px solid #f3f4f6;" class="relative rounded-3xl p-8 hover:-translate-y-2 transition-transform duration-500 group overflow-hidden lg:col-span-2">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gold opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="absolute -right-4 -bottom-4 pointer-events-none transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-12" style="color: rgba(74,102,46,0.03); font-size: 14rem; line-height: 1;">
                            <i class="fa-solid fa-leaf"></i>
                        </div>
                        <div class="relative z-10">
                            <div style="background-color: rgba(74,102,46,0.08); color: #4a662e;" class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform duration-500 shadow-sm">
                                <i class="fa-solid fa-leaf"></i>
                            </div>
                            <h4 style="color: #1a2310;" class="text-xl font-black mb-3 font-en uppercase tracking-wider" dir="ltr">Environmental</h4>
                            <p style="color: #52525b;" class="text-sm leading-relaxed font-medium max-w-2xl">
                                نقدم استشارات بيئية متخصصة وحلولاً مستدامة في مجال اللآندسكيب، بما يضمن تحقيق أعلى معايير الجودة البيئية للمشاريع.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Available Courses -->"""

with open(file_path, "w", encoding="utf-8") as f:
    f.write(prefix + new_middle + suffix)

print("Updated successfully!")
