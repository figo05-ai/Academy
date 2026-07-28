<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>@yield('title', 'لوحة تحكم المستخدم') - أكاديمية مكتب فني</title>
    <style nonce="{{ $csp_nonce }}">
        /* Re-using the same color palette and base styles */
        :root {
            --bg-primary: #07090f;
            --bg-secondary: #0c1020;
            --bg-card: rgba(14, 20, 38, 0.88);
            --glass-border: rgba(255, 255, 255, 0.07);
            --gold: #c9963a;
            --gold-light: #f0c76a;
            --gold-pale: rgba(201, 150, 58, 0.12);
            --blue: #3b7ef5;
            --text-primary: #edf2ff;
            --text-secondary: #7a92b8;
            --radius-card: 22px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image:
                radial-gradient(ellipse 40% 55% at 55% 50%, rgba(14, 20, 38, 0.6) 0%, transparent 80%);
        }

        /* Navbar */
        .navbar {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            background: rgba(7, 9, 15, 0.75);
            border-bottom: 1px solid var(--glass-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-title {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .user-name span {
            color: var(--text-primary);
            font-weight: 700;
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: transparent;
            color: var(--gold-light);
            border: 1px solid var(--gold-light);
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Cairo';
            cursor: pointer;
        }

        .btn-outline:hover {
            background: rgba(201, 150, 58, 0.1);
            transform: translateY(-2px);
        }

        .btn-logout {
            color: #eb5757;
            border-color: rgba(235, 87, 87, 0.4);
        }

        .btn-logout:hover {
            background: rgba(235, 87, 87, 0.1);
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            flex-grow: 1;
        }

        body.page-course-details .main-container {
            max-width: none;
            padding: 40px;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        body.page-course-details .page-footer {
            margin-top: 0; 
        }

        /* Section Header */
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title {
            font-size: clamp(24px, 5vw, 38px);
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.3;
            margin-bottom: 12px;
        }

        .section-title .highlight {
            background: linear-gradient(125deg, var(--gold-light) 0%, var(--gold) 60%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .section-subtitle {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Footer */
        .page-footer {
            text-align: center;
            padding: 28px 20px;
            margin-top: 50px;
            border-top: 1px solid var(--glass-border);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        /* Add specific styles for user pages here */
    </style>
    @yield('styles')
</head>

<body class="@yield('bodyClass')">
        <nav class="navbar">
        <div class="navbar-title"><i class="fa-solid fa-book-open-reader"></i> أكاديمية مكتب فني</div>
        <div class="user-menu">
            <div class="user-name">أهلاً بك، <span>{{ Auth::user()->name }}</span></div>
            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn-outline btn-logout">
                    <i class="fa-solid fa-right-from-bracket"></i> تسجيل الخروج
                </button>
            </form>
        </div>
    </nav>

    <main class="main-container">
        @yield('content')
    </main>

    <footer class="page-footer">
        جميع الحقوق محفوظة © أكاديمية مكتب فني {{ date('Y') }}
    </footer>

    @yield('scripts')
</body>

</html>
