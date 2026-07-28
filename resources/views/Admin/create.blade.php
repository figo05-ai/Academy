@extends('Admin.users.layouts.user')
@section('title', 'إضافة كورس جديد')

@section('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style nonce="{{ $csp_nonce }}">
    :root {
        --bg-primary: #07090f; --bg-secondary: #0c1020; --bg-card: rgba(14, 20, 38, 0.88);
        --glass-border: rgba(255, 255, 255, 0.1); --gold: #c9963a; --gold-light: #f0c76a;
        --blue: #3b7ef5; --text-primary: #edf2ff; --text-secondary: #7a92b8; --radius-card: 22px;
    }
    .admin-container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
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
</style>
@endsection

@section('content')
<div class="admin-container">
    <div class="admin-card">
        <h2><i class="fa-solid fa-plus"></i> إنشاء كورس جديد</h2>
        <form action="{{ route('admin.courses.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="title">عنوان الكورس</label>
                <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" required>
                @error('title') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="description">وصف الكورس</label>
                <textarea id="description" name="description" class="form-control" required>{{ old('description') }}</textarea>
                @error('description') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="price">السعر (EGP)</label>
                <input type="number" id="price" name="price" class="form-control" value="{{ old('price') }}" required min="0" step="0.01">
                @error('price') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="image">صورة الكورس</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                @error('image') <div class="error-message">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> إنشاء الكورس</button>
        </form>
    </div>
</div>
@endsection
