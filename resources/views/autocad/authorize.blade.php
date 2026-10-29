<!doctype html>
<html lang="ar" dir="rtl">
<meta charset="utf-8">
<meta name="referrer" content="no-referrer">
<meta name="viewport" content="width=device-width">
<title>ربط إضافة AutoCAD</title>

<body>
    <main>
        <h1>مكتبة أدوات المكتب الفني — AutoCAD</h1>
        <p>الحساب: {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>
        <p>وافق فقط إذا بدأت تسجيل الدخول من داخل AutoCAD وكان الرمز التالي مطابقًا للظاهر داخل الأداة:</p>
        <h2 dir="ltr">{{ $code }}</h2>
        <p>ربط الحساب لا يمنح ترخيصًا تلقائيًا؛ يجب أن يفعّل المسؤول ترخيص AutoCAD لحسابك.</p>
        <form method="post" action="{{ route('autocad.approve') }}">@csrf<input type="hidden" name="code"
                value="{{ $code }}"><button type="submit">تأكيد ربط حسابي بإضافة AutoCAD</button></form>
        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج لاستخدام حساب
                آخر</button></form>
    </main>
</body>

</html>
