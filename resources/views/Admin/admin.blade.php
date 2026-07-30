@extends('layouts.app')
@section('title', 'لوحة الإدارة - أكاديمية مكتب فني')
@section('page_title', 'لوحة الإدارة الأساسية')

@section('sidebar')
    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-text-secondary hover:bg-glass-bg hover:text-[#4a662e] transition font-semibold">
        <i class="fa-solid fa-users w-5 text-center"></i>
        إدارة المستخدمين
    </a>
    <button id="add-course-btn" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-text-secondary hover:bg-glass-bg hover:text-[#4a662e] transition font-semibold text-right">
        <i class="fa-solid fa-plus w-5 text-center"></i>
        إنشاء كورس
    </button>
@endsection

@section('content')
    <!-- Toast -->
    @if (session('success'))
        <div id="laravel-toast" class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-gold-light text-black px-6 py-3 rounded-full font-bold shadow-[0_5px_20px_rgba(201,150,58,0.4)] z-50 transition-opacity duration-500">{{ session('success') }}</div>
    @endif

    <div class="space-y-8" id="dashboard-view">
        
        <!-- Courses Card -->
        <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 shadow-lg">
            <h2 class="text-xl md:text-2xl font-bold text-[#4a662e] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-book-open"></i> إدارة الكورسات
            </h2>
            
            <div id="courses-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($courses as $course)
                    <div class="group bg-black/30 border border-glass-border rounded-2xl overflow-hidden hover:border-gold hover:-translate-y-1 transition-all duration-300 course-card" data-course-id="{{ $course->id }}" data-course-details="{{ $course->toJson() }}">
                        <img src="{{ asset($course->image_path ? 'storage/' . $course->image_path : 'images/default-course.jpg') }}" alt="{{ $course->title }}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500">
                        <div class="p-5">
                            <h3 class="text-lg font-bold text-[#4a662e] mb-2">{{ $course->title }}</h3>
                            <p class="text-sm text-text-secondary mb-4 leading-relaxed line-clamp-2">{{ $course->description }}</p>
                            <div class="flex gap-2 pt-4 border-t border-glass-border">
                                <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-blue-500/10 text-blue-700 hover:bg-blue-500 hover:text-white transition-colors flex items-center justify-center gap-2 btn-manage-sessions">
                                    <i class="fa-solid fa-list-check"></i> السيشنات
                                </button>
                                <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-gold/10 text-[#4a662e] hover:bg-gold-light hover:text-black transition-colors flex items-center justify-center gap-2 btn-edit-course">
                                    <i class="fa-solid fa-pen"></i> تعديل
                                </button>
                                <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-red-500/10 text-red-700 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center gap-2 btn-delete-course">
                                    <i class="fa-solid fa-trash"></i> حذف
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <p id="no-courses-message" class="col-span-full text-center text-text-secondary font-bold py-8">لا توجد كورسات مضافة حالياً.</p>
                @endforelse
            </div>
        </div>

        <!-- Links Settings -->
        <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 shadow-lg">
            <h2 class="text-xl md:text-2xl font-bold text-[#4a662e] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-link"></i> إدارة روابط البرامج (الرئيسية)
            </h2>
            <form action="{{ url('/admin/settings') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                
                <div class="space-y-2">
                    <label for="master_drive_link" class="block text-sm font-semibold text-text-secondary">رابط أعمال المتدربين العام (Google Drive)</label>
                    <input type="url" id="master_drive_link" name="master_drive_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('master_drive_link', $setting->master_drive_link) }}" placeholder="https://...">
                </div>
                
                <hr class="border-glass-border">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-text-secondary">رابط Sketchup & Lumion</label>
                        <input type="url" name="sketchup_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('sketchup_link', $setting->sketchup_link) }}" required>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-text-secondary">رابط 3Ds Max & Corona</label>
                        <input type="url" name="max_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('max_link', $setting->max_link) }}" required>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-text-secondary">رابط Autocad</label>
                        <input type="url" name="autocad_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('autocad_link', $setting->autocad_link) }}" required>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-text-secondary">رابط Manual Sketch</label>
                        <input type="url" name="manual_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('manual_link', $setting->manual_link) }}" required>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-text-secondary">رابط Real Time Landscape</label>
                        <input type="url" name="landscape_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" value="{{ old('landscape_link', $setting->landscape_link) }}" required>
                    </div>
                </div>
                
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 hover:shadow-lg hover:shadow-gold/20 transition-all">
                    <i class="fa-solid fa-save"></i> حفظ روابط البرامج
                </button>
            </form>
        </div>

        <!-- Projects Carousel Settings -->
        <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 shadow-lg">
            <h2 class="text-xl md:text-2xl font-bold text-[#4a662e] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-images"></i> إدارة أعمال المتدربين (الكاروسيل)
            </h2>
            
            <form action="{{ url('/admin/projects') }}" method="POST" enctype="multipart/form-data" class="space-y-4 max-w-2xl">
                @csrf
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">صورة المشروع</label>
                    <input type="file" name="image" class="w-full px-4 py-2.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-pale file:text-[#4a662e] hover:file:bg-gold/20 transition-colors" accept="image/*" required>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">رابط منشور الفيسبوك</label>
                    <input type="url" name="facebook_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" placeholder="https://..." required>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">رابط المشروع على جوجل درايف</label>
                    <input type="url" name="drive_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" placeholder="https://..." required>
                </div>
                
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 hover:shadow-lg hover:shadow-gold/20 transition-all">
                    <i class="fa-solid fa-plus"></i> إضافة مشروع جديد
                </button>
            </form>

            <hr class="border-glass-border my-8">

            <h3 class="text-lg font-bold text-text-primary mb-4">المشاريع المضافة حالياً</h3>
            <div class="overflow-x-auto rounded-xl border border-glass-border bg-black/20">
                <table class="w-full text-right whitespace-nowrap">
                    <thead class="bg-black/40 border-b border-glass-border">
                        <tr>
                            <th class="px-6 py-4 text-text-secondary font-bold">الصورة</th>
                            <th class="px-6 py-4 text-text-secondary font-bold">رابط الفيسبوك</th>
                            <th class="px-6 py-4 text-text-secondary font-bold">رابط الدرايف</th>
                            <th class="px-6 py-4 text-text-secondary font-bold">تحكم</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-glass-border">
                        @forelse ($projects as $project)
                            <tr class="hover:bg-glass-bg transition-colors">
                                <td class="px-6 py-4">
                                    <img src="{{ asset('storage/' . $project->image_path) }}" alt="Project" class="w-24 h-16 object-cover rounded-lg border border-glass-border">
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ $project->facebook_link }}" target="_blank" class="text-[#4a662e] hover:underline font-semibold flex items-center gap-1">رابط المنشور <i class="fa-solid fa-external-link text-[10px]"></i></a>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ $project->drive_link }}" target="_blank" class="text-[#4a662e] hover:underline font-semibold flex items-center gap-1">رابط الدرايف <i class="fa-solid fa-external-link text-[10px]"></i></a>
                                </td>
                                <td class="px-6 py-4">
                                    <form action="{{ route('admin.projects.destroy', $project->id) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المشروع؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-4 py-2 bg-red-500/10 text-red-700 hover:bg-red-500 hover:text-white rounded-lg font-semibold transition-colors flex items-center gap-2">
                                            <i class="fa-solid fa-trash"></i> حذف
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-text-muted font-semibold">لا توجد مشاريع مضافة حالياً.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Course Modal -->
    <div id="course-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm modal-bg"></div>
        <div class="relative bg-bg-card border border-glass-border rounded-2xl w-full max-w-lg p-6 md:p-8 shadow-2xl modal-content transform scale-95 opacity-0 transition-all duration-300">
            <div class="flex items-center justify-between mb-6">
                <h3 id="course-modal-title" class="text-xl font-bold text-[#4a662e]">إنشاء كورس جديد</h3>
                <button type="button" class="close-modal text-text-muted hover:text-red-700 transition-colors text-2xl">&times;</button>
            </div>
            
            <form id="course-form" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="_method" id="course-method-input">
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">اسم الكورس</label>
                    <input type="text" id="title-input" name="title" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold" required>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">صورة الكورس</label>
                    <input type="file" id="image-input" name="image" class="w-full px-4 py-2 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-pale file:text-[#4a662e] hover:file:bg-gold/20" accept="image/*">
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">وصف الكورس</label>
                    <textarea id="description-input" name="description" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold" required rows="4"></textarea>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-semibold text-text-secondary">السعر (EGP)</label>
                    <input type="number" id="price-input" name="price" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold" required min="0" step="0.01">
                </div>
                
                <button type="submit" class="w-full py-3 mt-4 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 hover:shadow-lg transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-save"></i> حفظ
                </button>
            </form>
        </div>
    </div>

    <!-- Sessions Modal -->
    <div id="sessions-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm modal-bg"></div>
        <div class="relative bg-bg-card border border-glass-border rounded-2xl w-full max-w-4xl p-6 md:p-8 shadow-2xl modal-content transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]">
            <div class="flex items-center justify-between mb-6 shrink-0 border-b border-glass-border pb-4">
                <h3 id="sessions-modal-title" class="text-xl font-bold text-[#4a662e]">إدارة السيشنات</h3>
                <button type="button" class="close-modal text-text-muted hover:text-red-700 transition-colors text-2xl">&times;</button>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8 overflow-hidden min-h-0">
                <!-- Add Session Form -->
                <div class="w-full md:w-1/3 shrink-0 overflow-y-auto pr-2">
                    <h4 class="text-lg font-bold text-[#4a662e] mb-4 flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة سيشن</h4>
                    <form id="session-form" class="space-y-4">
                        <datalist id="categories-list">
                            @foreach($categories as $category)
                                <option value="{{ $category }}">
                            @endforeach
                        </datalist>
                        <input type="hidden" name="session_id" id="session-id">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-text-secondary">القسم (Category)</label>
                            <input type="text" id="session-category-input" name="category_name" list="categories-list" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold" placeholder="اختر أو اكتب قسماً جديداً">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-text-secondary">اسم السيشن</label>
                            <input type="text" id="session-title-input" name="title" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold" required>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-text-secondary">لينك السيشن</label>
                            <input type="url" id="session-drive-link-input" name="drive_link" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold" placeholder="https://..." required>
                        </div>
                        
                        <button type="submit" class="w-full py-3 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 transition-all flex items-center justify-center gap-2 submit-btn">
                            <i class="fa-solid fa-plus"></i> إضافة
                        </button>
                        <button type="button" id="cancel-edit-session-btn" class="hidden w-full py-3 bg-transparent border border-glass-border text-text-secondary font-bold rounded-xl hover:bg-glass-bg transition-colors mt-2">
                            إلغاء التعديل
                        </button>
                    </form>
                </div>

                <!-- Sessions List -->
                <div class="flex-1 overflow-y-auto pr-2 md:pl-6 md:pr-0 md:border-r border-glass-border">
                    <h4 class="text-lg font-bold text-[#4a662e] mb-4 flex items-center gap-2"><i class="fa-solid fa-list-ul"></i> السيشنات المضافة</h4>
                    <ul id="sessions-list" class="space-y-3"></ul>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfToken = '{{ csrf_token() }}';

        // --- Modals logic mapped to Tailwind ---
        const courseModal = document.getElementById('course-modal');
        const sessionsModal = document.getElementById('sessions-modal');

        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.querySelector('.modal-content').classList.remove('scale-95', 'opacity-0');
                modal.querySelector('.modal-content').classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeModal(modal) {
            modal.querySelector('.modal-content').classList.remove('scale-100', 'opacity-100');
            modal.querySelector('.modal-content').classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        document.querySelectorAll('.close-modal, .modal-bg').forEach(btn => {
            btn.addEventListener('click', () => {
                closeModal(courseModal);
                closeModal(sessionsModal);
            });
        });

        // Toast logic
        const toast = document.getElementById('laravel-toast');
        if(toast) {
            setTimeout(() => { toast.classList.add('opacity-0'); setTimeout(() => toast.remove(), 500); }, 3000);
        }
        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'fixed bottom-8 left-1/2 -translate-x-1/2 bg-gold-light text-black px-6 py-3 rounded-full font-bold shadow-[0_5px_20px_rgba(201,150,58,0.4)] z-50 transition-all duration-500 opacity-0 translate-y-4';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => t.classList.remove('opacity-0', 'translate-y-4'), 10);
            setTimeout(() => { t.classList.add('opacity-0', 'translate-y-4'); setTimeout(() => t.remove(), 500); }, 3000);
        }

        // Add Course
        const courseForm = document.getElementById('course-form');
        document.getElementById('add-course-btn').addEventListener('click', () => {
            courseForm.reset();
            courseForm.action = '{{ route('admin.courses.store') }}';
            courseModal.querySelector('#course-modal-title').textContent = 'إنشاء كورس جديد';
            courseForm.querySelector('#course-method-input').value = 'POST';
            openModal(courseModal);
        });

        // Course Card Template
        function createCourseCard(course) {
            const defaultImage = '{{ asset('images/default-course.jpg') }}';
            const imageUrl = course.image_path ? `/storage/${course.image_path}` : defaultImage;
            return `
                <div class="group bg-black/30 border border-glass-border rounded-2xl overflow-hidden hover:border-gold hover:-translate-y-1 transition-all duration-300 course-card" data-course-id="${course.id}" data-course-details='${JSON.stringify(course)}'>
                    <img src="${imageUrl}" alt="${course.title}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-[#4a662e] mb-2">${course.title}</h3>
                        <p class="text-sm text-text-secondary mb-4 leading-relaxed line-clamp-2">${course.description}</p>
                        <div class="flex gap-2 pt-4 border-t border-glass-border">
                            <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-blue-500/10 text-blue-700 hover:bg-blue-500 hover:text-white transition-colors flex items-center justify-center gap-2 btn-manage-sessions">
                                <i class="fa-solid fa-list-check"></i> السيشنات
                            </button>
                            <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-gold/10 text-[#4a662e] hover:bg-gold-light hover:text-black transition-colors flex items-center justify-center gap-2 btn-edit-course">
                                <i class="fa-solid fa-pen"></i> تعديل
                            </button>
                            <button class="flex-1 py-2 text-sm font-semibold rounded-lg bg-red-500/10 text-red-700 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center gap-2 btn-delete-course">
                                <i class="fa-solid fa-trash"></i> حذف
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        const coursesGrid = document.getElementById('courses-grid');
        courseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const isEditing = formData.get('_method') === 'PUT';

            fetch(this.action, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast("تم الحفظ بنجاح");
                        closeModal(courseModal);
                        const newCardHTML = createCourseCard(data.course);
                        if (isEditing) {
                            const oldCard = coursesGrid.querySelector(`.course-card[data-course-id="${data.course.id}"]`);
                            if (oldCard) oldCard.outerHTML = newCardHTML;
                        } else {
                            const noMsg = document.getElementById('no-courses-message');
                            if(noMsg) noMsg.remove();
                            coursesGrid.insertAdjacentHTML('beforeend', newCardHTML);
                        }
                    } else { showToast('حدث خطأ ما.'); }
                }).catch(err => console.error(err));
        });

        // Courses actions
        let currentCourseIdForSessions = null;
        let sortable = null;

        coursesGrid.addEventListener('click', e => {
            const courseCard = e.target.closest('.course-card');
            if (!courseCard) return;
            const courseId = courseCard.dataset.courseId;
            const course = JSON.parse(courseCard.dataset.courseDetails);

            if (e.target.closest('.btn-edit-course')) {
                courseForm.reset();
                courseForm.action = `/admin/courses/${courseId}`;
                courseModal.querySelector('#course-modal-title').textContent = `تعديل: ${course.title}`;
                courseForm.querySelector('#course-method-input').value = 'PUT';
                courseForm.querySelector('#title-input').value = course.title;
                courseForm.querySelector('#description-input').value = course.description;
                courseForm.querySelector('#price-input').value = course.price;
                openModal(courseModal);
                return;
            }

            if (e.target.closest('.btn-delete-course')) {
                if (confirm('هل أنت متأكد من حذف هذا الكورس؟')) {
                    fetch(`/admin/courses/${courseId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) { showToast("تم الحذف"); courseCard.remove(); }
                        }).catch(err => console.error(err));
                }
                return;
            }

            if (e.target.closest('.btn-manage-sessions')) {
                currentCourseIdForSessions = courseId;
                sessionsModal.querySelector('#sessions-modal-title').textContent = `إدارة سيشنات: ${course.title}`;
                loadSessions(courseId);
                openModal(sessionsModal);
            }
        });

        // Sessions
        const sessionForm = document.getElementById('session-form');
        const sessionsListEl = document.getElementById('sessions-list');

        let sortables = [];

        function saveOrder() {
            const order = [];
            document.querySelectorAll('#sessions-list li[data-id]').forEach(item => {
                order.push(item.dataset.id);
            });
            fetch('{{ route('admin.sessions.reorder') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ order: order })
            }).then(res=>res.json()).then(data=>{ if(data.success) showToast("تم ترتيب السيشنات بنجاح"); });
        }

        function loadSessions(courseId) {
            fetch(`/admin/courses/${courseId}/sessions`)
                .then(res => res.json())
                .then(data => {
                    const sessionsData = data.data || data;
                    sessionsListEl.innerHTML = '';
                    
                    sortables.forEach(s => s.destroy());
                    sortables = [];

                    if (sessionsData && sessionsData.length > 0) {
                        const grouped = sessionsData.reduce((acc, s) => {
                            const cat = s.category_name || '';
                            if (!acc[cat]) acc[cat] = [];
                            acc[cat].push(s);
                            return acc;
                        }, {});

                        Object.keys(grouped).forEach((cat, index) => {
                            if (cat) {
                                const catDiv = document.createElement('div');
                                catDiv.className = 'category-group mb-4 bg-black/20 rounded-xl border border-glass-border overflow-hidden';
                                catDiv.innerHTML = `
                                    <button class="w-full px-4 py-3 bg-black/40 hover:bg-black/60 flex items-center justify-between transition-colors admin-category-toggle" data-target="admin-cat-${index}">
                                        <h4 class="text-sm font-bold text-[#DDEB9D]"><i class="fa-solid fa-folder-open ml-2 opacity-70"></i> ${cat}</h4>
                                        <i class="fa-solid fa-chevron-down text-text-muted transition-transform duration-300"></i>
                                    </button>
                                    <ul id="admin-cat-${index}" class="admin-category-content p-2 space-y-2 hidden"></ul>
                                `;
                                sessionsListEl.appendChild(catDiv);
                                const ul = catDiv.querySelector('ul');
                                grouped[cat].forEach(s => ul.appendChild(createSessionItem(s)));

                                sortables.push(new Sortable(ul, {
                                    animation: 150, handle: '.session-drag-handle',
                                    onEnd: saveOrder
                                }));
                            } else {
                                const ul = document.createElement('ul');
                                ul.className = 'space-y-2 mb-4';
                                grouped[cat].forEach(s => ul.appendChild(createSessionItem(s)));
                                sessionsListEl.appendChild(ul);
                                
                                sortables.push(new Sortable(ul, {
                                    animation: 150, handle: '.session-drag-handle',
                                    onEnd: saveOrder
                                }));
                            }
                        });

                        // Add Accordion Logic for Admin
                        document.querySelectorAll('.admin-category-toggle').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const targetId = this.dataset.target;
                                const targetContent = document.getElementById(targetId);
                                const icon = this.querySelector('i.fa-chevron-down');
                                const isHidden = targetContent.classList.contains('hidden');

                                document.querySelectorAll('.admin-category-content').forEach(c => c.classList.add('hidden'));
                                document.querySelectorAll('.admin-category-toggle i.fa-chevron-down').forEach(i => i.classList.remove('rotate-180'));

                                if (isHidden) {
                                    targetContent.classList.remove('hidden');
                                    icon.classList.add('rotate-180');
                                }
                            });
                        });
                        
                        // Expand first category by default
                        const firstToggle = document.querySelector('.admin-category-toggle');
                        if (firstToggle) firstToggle.click();

                    } else {
                        sessionsListEl.innerHTML = '<div class="text-center p-6 text-text-secondary font-bold">لا توجد سيشنات مضافة.</div>';
                    }
                    resetSessionForm();
                });
        }

        function createSessionItem(session) {
            const li = document.createElement('li');
            li.className = 'group bg-black/40 p-4 rounded-xl border border-glass-border flex items-center justify-between hover:border-gold-light transition-colors cursor-default';
            li.dataset.id = session.id;
            li.dataset.session = JSON.stringify(session);
            li.innerHTML = `
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-grip-vertical text-text-muted cursor-grab hover:text-[#4a662e] transition-colors session-drag-handle shrink-0"></i>
                    <div class="flex flex-col">
                        <span class="font-bold text-text-primary">${session.title}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button class="w-8 h-8 rounded-lg bg-gold/10 text-[#4a662e] hover:bg-gold-light hover:text-black transition-colors btn-edit-session" title="تعديل"><i class="fa-solid fa-pen text-sm"></i></button>
                    <button class="w-8 h-8 rounded-lg bg-red-500/10 text-red-700 hover:bg-red-500 hover:text-white transition-colors btn-delete-session" title="حذف"><i class="fa-solid fa-trash text-sm"></i></button>
                </div>
            `;
            return li;
        }

        sessionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const sessionId = this.querySelector('#session-id').value;
            const isEditing = !!sessionId;
            const url = isEditing ? `/admin/sessions/${sessionId}` : `/admin/courses/${currentCourseIdForSessions}/sessions`;
            const formData = new FormData(this);
            if(isEditing) formData.append('_method', 'PUT');

            fetch(url, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(isEditing ? 'تم التعديل بنجاح' : 'تم إضافة السيشن');
                        loadSessions(currentCourseIdForSessions);
                    }
                }).catch(err => console.error(err));
        });

        sessionsListEl.addEventListener('click', e => {
            const li = e.target.closest('li');
            if(!li) return;
            const session = JSON.parse(li.dataset.session);

            if (e.target.closest('.btn-edit-session')) {
                sessionForm.querySelector('#session-id').value = session.id;
                sessionForm.querySelector('#session-title-input').value = session.title;
                sessionForm.querySelector('#session-category-input').value = session.category_name || '';
                sessionForm.querySelector('#session-drive-link-input').value = session.drive_link || '';
                document.getElementById('cancel-edit-session-btn').classList.remove('hidden');
                sessionForm.querySelector('.submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> حفظ';
            }

            if (e.target.closest('.btn-delete-session')) {
                if(confirm('هل تريد حذف السيشن؟')) {
                    fetch(`/admin/sessions/${session.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
                        .then(res=>res.json()).then(data=>{ if(data.success) { showToast("تم الحذف"); li.remove(); } });
                }
            }
        });

        function resetSessionForm() {
            sessionForm.reset();
            sessionForm.querySelector('#session-id').value = '';
            document.getElementById('cancel-edit-session-btn').classList.add('hidden');
            sessionForm.querySelector('.submit-btn').innerHTML = '<i class="fa-solid fa-plus"></i> إضافة';
        }

        document.getElementById('cancel-edit-session-btn').addEventListener('click', resetSessionForm);
    });
</script>
@endsection
