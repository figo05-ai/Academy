@extends('Admin.users.layouts.user')
@section('title', 'تعديل الكورس: ' . $course->title)

@section('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style nonce="{{ $csp_nonce }}">
    :root {
        --bg-primary: #07090f; --bg-secondary: #0c1020; --bg-card: rgba(14, 20, 38, 0.88);
        --glass-border: rgba(255, 255, 255, 0.1); --gold: #c9963a; --gold-light: #f0c76a;
        --blue: #3b7ef5; --red: #eb5757; --text-primary: #edf2ff; --text-secondary: #7a92b8; --radius-card: 22px;
    }
    .admin-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
    .admin-card { background: var(--bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-card); padding: 30px; margin-bottom: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
    .admin-card h2 { margin-bottom: 24px; font-size: 22px; color: var(--gold-light); display: flex; align-items: center; gap: 10px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: var(--text-secondary); font-weight: 600; font-size: 14px; }
    .form-control { width: 100%; padding: 14px 18px; background: rgba(0,0,0,0.4); border: 1px solid var(--glass-border); border-radius: 12px; color: var(--text-primary); font-family: 'Cairo', sans-serif; font-size: 15px; transition: all 0.3s; }
    .form-control:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201, 150, 58, 0.15); }
    textarea.form-control { min-height: 120px; resize: vertical; }
    .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 28px; background: linear-gradient(135deg, var(--gold-light), var(--gold)); color: #07090f; border: none; border-radius: 12px; font-weight: 800; font-size: 16px; cursor: pointer; transition: all 0.3s ease; font-family: 'Cairo'; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201, 150, 58, 0.3); }
    .error-message { color: #eb5757; font-size: 13px; margin-top: 5px; }

    /* Sessions List */
    .sessions-list { list-style: none; margin-top: 20px; }
    .session-item { background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px 20px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
    .session-item.sortable-ghost { background: var(--gold-pale); }
    .session-info { display: flex; align-items: center; gap: 15px; }
    .session-drag-handle { cursor: move; color: var(--text-secondary); font-size: 18px; }
    .session-title { font-weight: 600; }
    .control-buttons { display: flex; gap: 8px; }
    .btn-action { padding: 6px 12px; border-radius: 8px; border: 1px solid transparent; font-family: 'Cairo'; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 5px; }
    .btn-edit { background: rgba(59, 126, 245, 0.1); color: var(--blue); border-color: rgba(59, 126, 245, 0.3); }
    .btn-edit:hover { background: var(--blue); color: #fff; }
    .btn-delete { background: rgba(235, 87, 87, 0.1); color: var(--red); border-color: rgba(235, 87, 87, 0.3); }
    .btn-delete:hover { background: var(--red); color: #fff; }
    .empty-state { text-align: center; padding: 30px; color: var(--text-secondary); font-weight: 600; border: 2px dashed var(--glass-border); border-radius: 12px; }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(8px); opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .modal-overlay.show { opacity: 1; visibility: visible; }
    .modal-content { background: var(--bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--radius-card); width: 100%; max-width: 600px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); transform: scale(0.95); transition: all 0.3s ease; }
    .modal-overlay.show .modal-content { transform: scale(1); }
    .modal-header { padding: 20px 30px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h3 { font-size: 18px; color: var(--gold-light); }
    .close-modal { background: none; border: none; color: var(--text-secondary); font-size: 28px; cursor: pointer; }
    .modal-body { padding: 30px; }
    .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(20px); background: var(--gold-light); color: #000; padding: 12px 30px; border-radius: 50px; font-weight: 700; opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 2000; box-shadow: 0 5px 20px rgba(201,150,58,0.4); pointer-events: none; }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
@endsection

@section('content')
<div class="admin-container">
    <!-- Edit Course Details -->
    <div class="admin-card">
        <h2><i class="fa-solid fa-pen-to-square"></i> تعديل بيانات الكورس</h2>
        <form action="{{ route('admin.courses.update', $course->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="title">عنوان الكورس</label>
                <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $course->title) }}" required>
            </div>
            <div class="form-group">
                <label for="description">وصف الكورس</label>
                <textarea id="description" name="description" class="form-control" required>{{ old('description', $course->description) }}</textarea>
            </div>
            <div class="form-group">
                <label for="price">السعر (EGP)</label>
                <input type="number" id="price" name="price" class="form-control" value="{{ old('price', $course->price) }}" required min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="image">تغيير صورة الكورس (اختياري)</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                @if($course->image_path)
                    <small class="text-secondary">الصورة الحالية:</small>
                    <img src="{{ asset('storage/' . $course->image_path) }}" alt="صورة الكورس" style="max-width: 150px; margin-top: 10px; border-radius: 8px;">
                @endif
            </div>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ التعديلات</button>
        </form>
    </div>

    <!-- Manage Sessions -->
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="margin-bottom: 0;"><i class="fa-solid fa-list-ul"></i> إدارة المحاضرات (اللينكات)</h2>
            <button id="add-session-btn" class="btn-primary"><i class="fa-solid fa-plus"></i> إضافة محاضرة</button>
        </div>
        <ul id="sessions-list" class="sessions-list">
            @forelse ($course->sessions->sortBy('order_number') as $session)
                <li class="session-item" data-id="{{ $session->id }}">
                    <div class="session-info">
                        <i class="fa-solid fa-grip-vertical session-drag-handle"></i>
                        <span class="session-title">{{ $session->title }}</span>
                    </div>
                    <div class="control-buttons">
                        <button class="btn-action btn-edit btn-edit-session" data-session="{{ $session }}"><i class="fa-solid fa-pen"></i> تعديل</button>
                        <button class="btn-action btn-delete btn-delete-session"><i class="fa-solid fa-trash"></i> حذف</button>
                    </div>
                </li>
            @empty
                <div class="empty-state">
                    <i class="fa-solid fa-video-slash fa-2x" style="margin-bottom: 10px;"></i>
                    <p>لا توجد محاضرات في هذا الكورس بعد.</p>
                </div>
            @endforelse
        </ul>
    </div>
</div>

<!-- Session Modal -->
<div id="session-modal" class="modal-overlay">
    <div class="modal-content">
        <form id="session-form">
            <div class="modal-header">
                <h3 id="modal-title">إضافة محاضرة جديدة</h3>
                <button type="button" class="close-modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="_method" id="session-method">
                <input type="hidden" name="session_id" id="session-id">
                <div class="form-group">
                    <label for="session-title-input">عنوان المحاضرة</label>
                    <input type="text" id="session-title-input" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="session-description-input">وصف قصير للمحاضرة (اختياري)</label>
                    <textarea id="session-description-input" name="description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="session-drive-link-input">رابط تحميل المحاضرة (Google Drive)</label>
                    <input type="url" id="session-drive-link-input" name="drive_link" class="form-control" placeholder="https://drive.google.com/...">
                </div>
                <div class="form-group">
                    <label for="session-task-link-input">رابط تسليم التاسك (Facebook Group)</label>
                    <input type="url" id="session-task-link-input" name="facebook_group_link" class="form-control" placeholder="https://facebook.com/groups/...">
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 30px; border-top: 1px solid var(--glass-border); text-align: left;">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

<div id="toast-notification" class="toast"></div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" integrity="sha256-ipiJrswvAR4VA/N+2Ok0w9xI/L8JIPY0F27Vl3mfs/c=" crossorigin="anonymous"></script>
<script nonce="{{ $csp_nonce }}">
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('session-modal');
    const sessionForm = document.getElementById('session-form');
    const modalTitle = document.getElementById('modal-title');
    const csrfToken = '{{ csrf_token() }}';

    // Toast Notification
    const toast = document.getElementById('toast-notification');
    function showToast(message) {
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Open "Add Session" modal
    document.getElementById('add-session-btn').addEventListener('click', () => {
        sessionForm.reset();
        sessionForm.action = "{{ route('admin.sessions.store', $course->id) }}";
        document.getElementById('session-method').value = 'POST';
        modalTitle.textContent = 'إضافة محاضرة جديدة';
        modal.classList.add('show');
    });

    // Open "Edit Session" modal
    document.querySelectorAll('.btn-edit-session').forEach(btn => {
        btn.addEventListener('click', function() {
            const session = JSON.parse(this.dataset.session);
            sessionForm.reset();
            sessionForm.action = `/admin/sessions/${session.id}`;
            document.getElementById('session-method').value = 'PUT';
            modalTitle.textContent = 'تعديل المحاضرة';

            document.getElementById('session-id').value = session.id;
            document.getElementById('session-title-input').value = session.title;
            document.getElementById('session-description-input').value = session.description || '';
            document.getElementById('session-drive-link-input').value = session.drive_link || '';
            document.getElementById('session-task-link-input').value = session.facebook_group_link || '';

            modal.classList.add('show');
        });
    });

    // Close modal
    modal.addEventListener('click', function (e) {
        if (e.target.classList.contains('close-modal') || e.target.classList.contains('modal-overlay')) {
            modal.classList.remove('show');
        }
    });

    // Handle Form Submission (Add/Edit)
    sessionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        // For edit, we use AJAX. For add, we do a full page reload.
        if (document.getElementById('session-method').value === 'PUT') {
            const formData = new FormData(this);
            fetch(this.action, {
                method: 'POST', // HTML forms don't support PUT, so we use POST and a hidden _method field
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.success);
                    setTimeout(() => window.location.reload(), 1000);
                }
            }).catch(err => console.error(err));
        } else {
            this.submit(); // Standard form submission for adding a new session
        }
    });

    // Handle Session Deletion
    document.querySelectorAll('.btn-delete-session').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('هل أنت متأكد من حذف هذه المحاضرة؟')) {
                const sessionId = this.closest('.session-item').dataset.id;
                fetch(`/admin/sessions/${sessionId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.closest('.session-item').remove();
                        showToast(data.success);
                    }
                }).catch(err => console.error(err));
            }
        });
    });

    // SortableJS for reordering
    const list = document.getElementById('sessions-list');
    if (list) {
        new Sortable(list, {
            animation: 150,
            handle: '.session-drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const order = Array.from(list.children).map(item => item.dataset.id);
                fetch('{{ route("admin.sessions.reorder") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ order: order })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) showToast(data.success);
                });
            }
        });
    }
});
</script>
@endsection
