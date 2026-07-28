<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <title>404 - الصفحة غير موجودة</title>
    <style nonce="{{ $csp_nonce }}">
        :root {
            --bg-primary: #07090f;
            --bg-card: rgba(14, 20, 38, 0.88);
            --glass-border: rgba(255, 255, 255, 0.07);
            --gold: #c9963a;
            --gold-light: #f0c76a;
            --text-primary: #edf2ff;
            --text-secondary: #7a92b8;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            background-image: radial-gradient(ellipse 40% 55% at 55% 50%, rgba(14, 20, 38, 0.6) 0%, transparent 80%);
        }
        .error-card {
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 22px;
            padding: 40px 50px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            text-align: center;
        }
        .error-code {
            font-size: 120px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(201, 150, 58, 0.2);
        }
        .error-card h2 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 700;
        }
        .error-card p {
            color: var(--text-secondary);
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.7;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 35px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            color: #07090f;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Cairo';
            text-decoration: none;
        }
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(201, 150, 58, 0.35);
        }
    </style>
</head>
<body>

    <div class="error-card">
        <div class="error-code">404</div>
        <h2>الصفحة غير موجودة</h2>
        <p>عذراً، الصفحة التي تبحث عنها غير موجودة أو ربما تم نقلها.</p>
        <a href="{{ url('/') }}" class="btn-home">
            <i class="fa-solid fa-house"></i> العودة للرئيسية
        </a>
    </div>

</body>
</html>
