<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Tarakan Art Class</title>
    <script>
        (function () {
            var t = localStorage.getItem('tac-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --grad-from: #38BDF8;
            --grad-to: #2563EB;
            --card-bg: rgba(255, 255, 255, 0.96);
            --card-shadow: rgba(37, 99, 235, 0.25);
            --title-color: #0F172A;
            --subtitle-color: #64748B;
            --input-bg: #F1F5F9;
            --input-bg-focus: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --label-color: #94A3B8;
            --toggle-color: #94A3B8;
        }

        [data-bs-theme="dark"] {
            --grad-from: #0C4A6E;
            --grad-to: #1E3A8A;
            --card-bg: rgba(17, 30, 51, 0.94);
            --card-shadow: rgba(0, 0, 0, 0.5);
            --title-color: #F1F5F9;
            --subtitle-color: #94A3B8;
            --input-bg: #16243C;
            --input-bg-focus: #1B2C48;
            --input-border: #22334E;
            --input-text: #E2E8F0;
            --label-color: #94A3B8;
            --toggle-color: #94A3B8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--grad-from) 0%, var(--grad-to) 100%);
            overflow: hidden;
            position: relative;
            transition: background 0.4s ease;
        }

        /* Theme toggle floating button */
        .theme-toggle {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }
        .theme-toggle:hover { background: rgba(255, 255, 255, 0.28); transform: rotate(15deg); }

        /* Animated Background Shapes */
        .bg-shapes { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden; }
        .bg-shapes .shape { position: absolute; border-radius: 50%; opacity: 0.12; background: #fff; animation: float 20s infinite ease-in-out; }
        .bg-shapes .shape:nth-child(1) { width: 400px; height: 400px; top: -100px; left: -100px; animation-duration: 25s; }
        .bg-shapes .shape:nth-child(2) { width: 300px; height: 300px; bottom: -80px; right: -80px; animation-duration: 20s; animation-delay: 2s; }
        .bg-shapes .shape:nth-child(3) { width: 200px; height: 200px; top: 50%; left: 60%; animation-duration: 18s; animation-delay: 4s; }
        .bg-shapes .shape:nth-child(4) { width: 150px; height: 150px; top: 20%; right: 15%; animation-duration: 22s; animation-delay: 1s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-30px) rotate(5deg); }
            50% { transform: translateY(15px) rotate(-5deg); }
            75% { transform: translateY(-20px) rotate(3deg); }
        }

        /* Login Card */
        .login-wrapper { position: relative; z-index: 1; width: 100%; max-width: 460px; padding: 1rem; }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px var(--card-shadow);
            padding: 3rem 2.5rem;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Brand */
        .brand-section { text-align: center; margin-bottom: 2rem; }

        .brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--grad-from), var(--grad-to));
            color: #fff;
            border-radius: 1rem;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        }

        .brand-title { font-weight: 900; font-size: 1.6rem; color: var(--title-color); letter-spacing: -0.5px; }
        .brand-subtitle { color: var(--subtitle-color); font-size: 0.9rem; font-weight: 400; margin-top: 0.25rem; }

        /* Form */
        .form-floating > .form-control {
            border: 2px solid var(--input-border);
            border-radius: 0.75rem;
            padding: 1rem 1rem 0.5rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: var(--input-bg);
            color: var(--input-text);
        }

        .form-floating > .form-control:focus {
            border-color: #0EA5E9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.18);
            background-color: var(--input-bg-focus);
            color: var(--input-text);
        }

        .form-floating > label { color: var(--label-color); font-weight: 600; padding: 1rem; }

        /* Login Button */
        .btn-login {
            background: linear-gradient(135deg, var(--grad-from), var(--grad-to));
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.85rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45); color: #fff; }
        .btn-login:active { transform: translateY(0); }
        .btn-login .spinner-border { display: none; }
        .btn-login.loading .spinner-border { display: inline-block; }
        .btn-login.loading .btn-text { display: none; }

        /* Remember Me */
        .form-check-input:checked { background-color: #0EA5E9; border-color: #0EA5E9; }
        .form-check-label { font-size: 0.9rem; color: var(--subtitle-color); cursor: pointer; }

        /* Alert */
        .alert-danger {
            background-color: #FEF2F2;
            border: 1px solid #FEE2E2;
            color: #991B1B;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
        }
        [data-bs-theme="dark"] .alert-danger {
            background-color: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.3);
            color: #FCA5A5;
        }

        /* Footer */
        .login-footer { text-align: center; margin-top: 2rem; color: rgba(255, 255, 255, 0.75); font-size: 0.85rem; }
        .login-footer a { color: #fff; text-decoration: none; font-weight: 600; }

        /* Password toggle */
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
            cursor: pointer;
            color: var(--toggle-color);
            background: none;
            border: none;
            padding: 0;
            font-size: 1.1rem;
        }
        .password-toggle:hover { color: #0EA5E9; }
    </style>
</head>
<body>

    <!-- Theme toggle -->
    <button type="button" class="theme-toggle" id="themeToggle" title="Ganti Tema Terang / Gelap">
        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
    </button>

    <!-- Animated Background -->
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Brand -->
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="bi bi-palette-fill"></i>
                </div>
                <h1 class="brand-title">Tarakan Art Class</h1>
                <p class="brand-subtitle">Masuk ke Dashboard Administrasi</p>
            </div>

            <!-- Error Message -->
            @if($errors->any())
                <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <!-- Login Form -->
            <form action="{{ url('/login') }}" method="POST" id="loginForm">
                @csrf

                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="login" name="login" placeholder="Email atau Username" value="{{ old('login') }}" required autofocus>
                    <label for="login"><i class="bi bi-person me-1"></i> Email atau Username</label>
                </div>

                <div class="form-floating mb-3 password-wrapper">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-1"></i> Password</label>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat Saya</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100" id="loginBtn">
                    <span class="btn-text"><i class="bi bi-box-arrow-in-right me-1"></i> Masuk</span>
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                </button>
            </form>
        </div>

        <div class="login-footer">
            <p>&copy; {{ date('Y') }} Tarakan Art Class. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Button loading state on submit
        document.getElementById('loginForm').addEventListener('submit', function () {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Light / Dark theme toggle
        (function () {
            const toggle = document.getElementById('themeToggle');
            const icon = document.getElementById('themeIcon');
            function syncIcon() {
                const theme = document.documentElement.getAttribute('data-bs-theme');
                icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            }
            syncIcon();
            toggle.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-bs-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                localStorage.setItem('tac-theme', next);
                syncIcon();
            });
        })();
    </script>
</body>
</html>
