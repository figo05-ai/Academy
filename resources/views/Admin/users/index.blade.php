@extends('layouts.app')
@section('title', 'إدارة المستخدمين - أكاديمية مكتب فني')
@section('page_title', 'إدارة المستخدمين')

@section('sidebar')
    <a href="{{ url('/admin') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-text-secondary hover:bg-glass-bg hover:text-[#4a662e] transition font-semibold">
        <i class="fa-solid fa-arrow-right w-5 text-center"></i>
        العودة للوحة الإدارة
    </a>
@endsection

@section('content')
<div class="space-y-8">
    <div class="bg-bg-card border border-glass-border rounded-2xl p-6 md:p-8 shadow-lg">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <h2 class="text-xl md:text-2xl font-bold text-[#4a662e] flex items-center gap-3"><i class="fa-solid fa-table-list"></i> قائمة المستخدمين</h2>
            
            <form action="{{ route('admin.users.index') }}" method="GET" class="w-full md:w-auto flex gap-2">
                <input type="text" name="search" class="w-full md:w-80 px-4 py-2.5 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold focus:ring-1 focus:ring-gold transition-colors" placeholder="ابحث بالاسم أو البريد..." value="{{ request('search') }}">
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-br from-gold-light to-gold text-text-primary font-bold rounded-xl hover:-translate-y-1 hover:shadow-lg hover:shadow-gold/20 transition-all flex items-center gap-2"><i class="fa-solid fa-search"></i> بحث</button>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-glass-border bg-black/20">
            <table class="w-full text-right whitespace-nowrap">
                <thead class="bg-black/40 border-b border-glass-border">
                    <tr>
                        <th class="px-6 py-4 text-text-secondary font-bold">الطالب</th>
                        <th class="px-6 py-4 text-text-secondary font-bold">البريد الإلكتروني</th>
                        <th class="px-6 py-4 text-text-secondary font-bold">الكورسات</th>
                        <th class="px-6 py-4 text-text-secondary font-bold">تاريخ التسجيل</th>
                        <th class="px-6 py-4 text-text-secondary font-bold">الحالة</th>
                        <th class="px-6 py-4 text-text-secondary font-bold">تحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-glass-border">
                    @forelse ($users as $user)
                        <tr class="hover:bg-glass-bg transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-bg-secondary flex items-center justify-center font-bold text-[#4a662e] shrink-0">{{ mb_substr($user->name, 0, 1) }}</div>
                                    <span class="font-semibold text-text-primary">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-text-primary">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 bg-blue-500/10 text-blue-700 rounded-lg font-bold text-sm">{{ $user->courses->count() }} كورسات</span>
                            </td>
                            <td class="px-6 py-4 text-text-primary">{{ $user->created_at->format('Y-m-d') }}</td>
                            <td class="px-6 py-4">
                                <button class="btn-toggle-status flex items-center gap-2 px-3 py-1.5 rounded-lg font-bold text-sm transition-colors border {{ $user->is_active ? 'bg-green-500/10 text-green-700 border-green-500/30 hover:bg-green-500 hover:text-white' : 'bg-gray-500/10 text-text-secondary border-gray-500/30 hover:bg-text-secondary hover:text-text-primary' }}" data-user-id="{{ $user->id }}">
                                    @if ($user->is_active)
                                        <i class="fa-solid fa-check-circle"></i> <span>نشط</span>
                                    @else
                                        <i class="fa-solid fa-times-circle"></i> <span>غير نشط</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <button class="btn-edit-user px-3 py-1.5 bg-blue-500/10 text-blue-700 hover:bg-blue-500 hover:text-white border border-blue-500/30 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-user-email="{{ $user->email }}">
                                        <i class="fa-solid fa-pen"></i> تعديل
                                    </button>
                                    <button class="btn-delete-user px-3 py-1.5 bg-red-500/10 text-red-700 hover:bg-red-500 hover:text-white border border-red-500/30 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2" data-user-id="{{ $user->id }}">
                                        <i class="fa-solid fa-trash"></i> حذف
                                    </button>
                                    <button class="btn-courses px-3 py-1.5 bg-purple-500/10 text-purple-700 hover:bg-purple-500 hover:text-white border border-purple-500/30 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-user-courses="{{ json_encode($user->courses->pluck('id')) }}">
                                        <i class="fa-solid fa-graduation-cap"></i> الكورسات
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-text-secondary font-bold">
                                <i class="fa-solid fa-users-slash text-4xl mb-4 opacity-50 block"></i>
                                لا يوجد مستخدمين لعرضهم حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex justify-center custom-pagination">
            {{ $users->appends(request()->query())->links() }}
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm modal-bg"></div>
    <div class="relative bg-bg-card border border-glass-border rounded-2xl w-full max-w-lg p-6 md:p-8 shadow-2xl modal-content transform scale-95 opacity-0 transition-all duration-300">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-[#4a662e]">تعديل بيانات المستخدم</h3>
            <button type="button" class="close-modal text-text-muted hover:text-red-700 transition-colors text-2xl">&times;</button>
        </div>
        <form id="edit-user-form" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="space-y-2">
                <label for="edit-name" class="block text-sm font-semibold text-text-secondary">الاسم</label>
                <input type="text" id="edit-name" name="name" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold" required>
            </div>
            <div class="space-y-2">
                <label for="edit-email" class="block text-sm font-semibold text-text-secondary">البريد الإلكتروني</label>
                <input type="email" id="edit-email" name="email" class="w-full px-4 py-3 bg-black/40 border border-glass-border rounded-xl text-text-primary focus:border-gold" required>
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-glass-border">
                <button type="button" class="px-6 py-2.5 bg-transparent border border-glass-border text-text-secondary font-bold rounded-xl hover:bg-glass-bg transition-colors close-modal">إلغاء</button>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 transition-all shadow-lg hover:shadow-gold/20">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<!-- Courses Modal -->
<div id="courses-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm modal-bg"></div>
    <div class="relative bg-bg-card border border-glass-border rounded-2xl w-full max-w-4xl p-6 md:p-8 shadow-2xl modal-content transform scale-95 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between mb-6 shrink-0 pb-4 border-b border-glass-border">
            <h3 class="text-xl font-bold text-[#4a662e]">إدارة كورسات: <span id="modal-user-name" class="text-text-primary ml-1"></span></h3>
            <button type="button" class="close-modal text-text-muted hover:text-red-700 transition-colors text-2xl">&times;</button>
        </div>
        <form id="courses-form" class="flex flex-col min-h-0">
            @csrf
            @method('PUT')
            <div class="flex flex-col md:flex-row gap-6 overflow-hidden min-h-0 mb-6">
                <div class="flex-1 bg-black/30 p-4 border border-glass-border rounded-xl flex flex-col min-h-0">
                    <h4 class="text-text-secondary font-bold mb-4 shrink-0">الكورسات المتاحة</h4>
                    <ul id="available-courses-list" class="space-y-2 overflow-y-auto pr-2 custom-scrollbar flex-1"></ul>
                </div>
                <div class="flex-1 bg-black/30 p-4 border border-glass-border rounded-xl flex flex-col min-h-0">
                    <h4 class="text-text-secondary font-bold mb-4 shrink-0">الكورسات المشترك بها</h4>
                    <ul id="enrolled-courses-list" class="space-y-2 overflow-y-auto pr-2 custom-scrollbar flex-1"></ul>
                </div>
            </div>
            <div class="flex justify-end gap-3 shrink-0 pt-4 border-t border-glass-border">
                <button type="button" class="px-6 py-2.5 bg-transparent border border-glass-border text-text-secondary font-bold rounded-xl hover:bg-glass-bg transition-colors close-modal">إلغاء</button>
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-br from-gold-light to-gold text-text-primary font-black rounded-xl hover:-translate-y-1 transition-all shadow-lg hover:shadow-gold/20">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div id="toast-notification" class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-gold-light text-black px-6 py-3 rounded-full font-bold shadow-[0_5px_20px_rgba(201,150,58,0.4)] z-[2000] transition-all duration-500 opacity-0 translate-y-4 pointer-events-none"></div>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>
<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.1); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(201, 150, 58, 0.3); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(201, 150, 58, 0.5); }

/* Custom Pagination Tailwind override */
.custom-pagination nav { display: flex; gap: 4px; }
.custom-pagination nav span[aria-current="page"] span { background: var(--gold); color: #000; border-color: var(--gold); }
.custom-pagination nav a { background: var(--bg-secondary); color: var(--text-secondary); border-color: var(--glass-border); transition: all 0.3s; }
.custom-pagination nav a:hover { background: var(--gold-pale); color: var(--gold-light); border-color: var(--gold); }
</style>
<script nonce="{{ $csp_nonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const allCourses = @json($allCourses);
    const modal = document.getElementById('courses-modal');
    const modalUserName = document.getElementById('modal-user-name');
    const availableList = document.getElementById('available-courses-list');
    const enrolledList = document.getElementById('enrolled-courses-list');
    const editUserModal = document.getElementById('edit-user-modal');
    const editUserForm = document.getElementById('edit-user-form');
    const coursesForm = document.getElementById('courses-form');
    let currentUserId = null;

    const toast = document.getElementById('toast-notification');
    function showToast(message) {
        toast.textContent = message;
        toast.classList.remove('opacity-0', 'translate-y-4');
        setTimeout(() => toast.classList.add('opacity-0', 'translate-y-4'), 3000);
    }

    function openModal(el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        setTimeout(() => {
            el.querySelector('.modal-content').classList.remove('scale-95', 'opacity-0');
        }, 10);
    }

    function closeModal(el) {
        el.querySelector('.modal-content').classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            el.classList.add('hidden');
            el.classList.remove('flex');
        }, 300);
    }

    document.querySelectorAll('.close-modal, .modal-bg').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if(e.target === e.currentTarget || e.target.classList.contains('close-modal')){
                closeModal(modal);
                closeModal(editUserModal);
            }
        });
    });

    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.btn-courses')) {
            const button = e.target.closest('.btn-courses');
            currentUserId = button.dataset.userId;
            modalUserName.textContent = button.dataset.userName;
            coursesForm.action = `/admin/users/${currentUserId}/courses`;
            populateLists(JSON.parse(button.dataset.userCourses));
            openModal(modal);
        }

        if (e.target.closest('.btn-edit-user')) {
            const button = e.target.closest('.btn-edit-user');
            editUserForm.action = `/admin/users/${button.dataset.userId}`;
            editUserForm.querySelector('#edit-name').value = button.dataset.userName;
            editUserForm.querySelector('#edit-email').value = button.dataset.userEmail;
            openModal(editUserModal);
        }

        if (e.target.closest('#available-courses-list li')) {
            const li = e.target.closest('li');
            enrolledList.appendChild(li);
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-trash-can text-red-700 hover:text-red-500 hover:scale-110 transition-transform delete-course ml-auto';
            li.appendChild(icon);
        }

        if (e.target.closest('.delete-course')) {
            const icon = e.target.closest('.delete-course');
            const li = icon.closest('li');
            icon.remove();
            availableList.appendChild(li);
        }

        if (e.target.closest('.btn-toggle-status')) {
            const button = e.target.closest('.btn-toggle-status');
            fetch(`/admin/users/${button.dataset.userId}/toggle-status`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    const icon = button.querySelector('i');
                    const text = button.querySelector('span');
                    if (data.is_active) {
                        button.className = 'btn-toggle-status flex items-center gap-2 px-3 py-1.5 rounded-lg font-bold text-sm transition-colors border bg-green-500/10 text-green-700 border-green-500/30 hover:bg-green-500 hover:text-white';
                        icon.className = 'fa-solid fa-check-circle';
                        text.textContent = 'نشط';
                    } else {
                        button.className = 'btn-toggle-status flex items-center gap-2 px-3 py-1.5 rounded-lg font-bold text-sm transition-colors border bg-gray-500/10 text-text-secondary border-gray-500/30 hover:bg-text-secondary hover:text-text-primary';
                        icon.className = 'fa-solid fa-times-circle';
                        text.textContent = 'غير نشط';
                    }
                    showToast(data.success);
                }
            }).catch(console.error);
        }

        if (e.target.closest('.btn-delete-user')) {
            const button = e.target.closest('.btn-delete-user');
            Swal.fire({
                title: 'هل أنت متأكد؟', text: "لا يمكن التراجع عن حذف هذا المستخدم!",
                icon: 'warning', showCancelButton: true, confirmButtonText: 'نعم، احذفه!', cancelButtonText: 'إلغاء',
                confirmButtonColor: '#d3b574', cancelButtonColor: '#eb5757', background: '#0e1426', color: '#edf2ff'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/admin/users/${button.dataset.userId}`, { method: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'} })
                    .then(res => res.json().then(data => ({ status: res.status, body: data })))
                    .then(({ status, body }) => {
                        showToast(body.success || body.error || 'حدث خطأ ما.');
                        if (status === 200 && body.success) button.closest('tr').remove();
                    }).catch(console.error);
                }
            });
        }
    });

    function populateLists(userCourseIds) {
        availableList.innerHTML = ''; enrolledList.innerHTML = '';
        allCourses.forEach(course => {
            const li = document.createElement('li');
            li.className = 'p-3 bg-bg-secondary rounded-lg cursor-pointer hover:bg-white/5 transition-colors flex items-center justify-between text-text-primary font-semibold mb-2';
            li.dataset.courseId = course.id;
            li.textContent = course.title;
            if (userCourseIds.includes(course.id)) {
                li.innerHTML += '<i class="fa-solid fa-trash-can text-red-700 hover:text-red-500 hover:scale-110 transition-transform delete-course ml-auto"></i>';
                enrolledList.appendChild(li);
            } else {
                availableList.appendChild(li);
            }
        });
    }

    coursesForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.delete('course_ids[]');
        Array.from(enrolledList.querySelectorAll('li')).forEach(li => formData.append('course_ids[]', li.dataset.courseId));
        fetch(this.action, { method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} })
            .then(res => res.json())
            .then(data => { showToast(data.success || 'حدث خطأ ما.'); if(data.success) setTimeout(() => window.location.reload(), 1500); })
            .catch(console.error);
    });

    editUserForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch(this.action, { method: 'POST', body: new FormData(this), headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.success);
                closeModal(editUserModal);
                const row = document.querySelector(`.btn-edit-user[data-user-id="${this.action.split('/').pop()}"]`).closest('tr');
                if (row) {
                    row.querySelector('.font-semibold').textContent = data.user.name;
                    row.querySelector('td:nth-child(2)').textContent = data.user.email;
                    const editBtn = row.querySelector('.btn-edit-user');
                    editBtn.dataset.userName = data.user.name;
                    editBtn.dataset.userEmail = data.user.email;
                }
            } else {
                Swal.fire({title:'خطأ في الإدخال', text:data.message || Object.values(data.errors).join('\n'), icon:'error', background: '#0e1426', color: '#edf2ff'});
            }
        }).catch(console.error);
    });
});
</script>
@endsection
