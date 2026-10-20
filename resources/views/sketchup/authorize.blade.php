<!doctype html>
<html lang="ar" dir="rtl">
<meta charset="utf-8">
<meta name="referrer" content="no-referrer">
<meta name="viewport" content="width=device-width">
<title>ربط إضافة SketchUp</title>

<body>
    <main>
        <h1>مكتبة أدوات المكتب الفني — SketchUp</h1>
        <p>الحساب: {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>
        <p>وافق فقط إذا بدأت تسجيل الدخول من إضافتك وكان الرمز التالي مطابقًا للظاهر داخل SketchUp:</p>
        <h2 dir="ltr">{{ $code }}</h2>
        <p>ربط الحساب لا يمنح ترخيصًا؛ يجب أن يفعّل المسؤول هذه الإضافة لحسابك.</p>
        <form method="post" action="{{ route('sketchup.approve') }}">@csrf<input type="hidden" name="code"
                value="{{ $code }}"><button type="submit">تأكيد ربط حسابي بإضافة SketchUp</button></form>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج لاستخدام حساب
                آخر</button></form>
    </main>
</body>

</html>
