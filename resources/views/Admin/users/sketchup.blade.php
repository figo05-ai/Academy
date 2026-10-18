@extends('layouts.app')
@section('content')
<div class="p-6"><h1>ترخيص مكتبة أدوات المكتب الفني — SketchUp</h1><p>{{ $user->name }} — {{ $user->email }}</p>
<p>هذا الترخيص مستقل عن Maxtree والكورسات. يلزم أيضًا أن يكون الحساب نشطًا.</p>
<form id="sketchup-license" action="{{ route('admin.users.sketchup.update', $user) }}">@csrf
<label><input type="checkbox" name="is_active" @checked($user->sketchupLicense?->is_active)> تفعيل الإضافة</label>
<label>انتهاء الترخيص (بتوقيت الموقع {{ config('app.timezone') }})<input type="datetime-local" name="expires_at" value="{{ $user->sketchupLicense?->expires_at?->format('Y-m-d\TH:i') }}"></label>
<p>اترك تاريخ الانتهاء فارغًا لترخيص بلا موعد انتهاء؛ تسجيل الدخول والتحقق الدوري يظلان إلزاميين.</p>
<label>ملاحظات<textarea name="notes" maxlength="1000">{{ $user->sketchupLicense?->notes }}</textarea></label>
<button type="submit">حفظ الترخيص</button><p id="result" role="status"></p></form><a href="{{ route('admin.users.index') }}">العودة للمستخدمين</a></div>
<script>
document.getElementById('sketchup-license').addEventListener('submit',async function(e){
 e.preventDefault();const out=document.getElementById('result');out.textContent='جارٍ الحفظ…';
 try{const response=await fetch(this.action,{method:'PUT',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':this.elements._token.value},body:JSON.stringify({is_active:this.elements.is_active.checked,expires_at:this.elements.expires_at.value||null,notes:this.elements.notes.value})});
 out.textContent=response.ok?'تم حفظ ترخيص SketchUp.':'تعذر الحفظ؛ تحقق من البيانات وصلاحية جلسة المسؤول.';
 }catch(e){out.textContent='تعذر الاتصال. لم يتم تأكيد حفظ الترخيص.';}
});
</script>
@endsection
