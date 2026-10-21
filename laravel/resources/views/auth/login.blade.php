<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Login &mdash; {{ setting('company_name', 'MyCron') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #4f46e5;
            --brand-secondary: #7c3aed;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #0f172a;
            background-image:
                radial-gradient(at 0% 0%, rgba(79, 70, 229, 0.35) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(124, 58, 237, 0.25) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.2) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(79, 70, 229, 0.2) 0px, transparent 50%);
            padding: 1.5rem;
            color: #1e293b;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            padding: 2.5rem 2.25rem;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.5);
            margin-bottom: 1.25rem;
        }

        .login-title {
            font-weight: 700;
            font-size: 1.5rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.75rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.825rem;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .input-group-modern {
            position: relative;
        }

        .input-group-modern .input-icon {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.05rem;
            z-index: 4;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input-group-modern .form-control {
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border-radius: 0.75rem;
            border: 1.5px solid #e2e8f0;
            font-size: 0.925rem;
            background-color: #f8fafc;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .input-group-modern .form-control.has-toggle {
            padding-right: 2.75rem;
        }

        .input-group-modern .form-control:focus {
            background-color: #fff;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        .input-group-modern:focus-within .input-icon {
            color: var(--brand-primary);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            padding: 0.25rem 0.4rem;
            cursor: pointer;
            z-index: 4;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #475569;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            border: none;
            border-radius: 0.75rem;
            padding: 0.8rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.45);
            transition: all 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px -5px rgba(79, 70, 229, 0.55);
            color: #fff;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-modern {
            border-radius: 0.75rem;
            border: 1px solid #fecaca;
            background-color: #fef2f2;
            color: #b91c1c;
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.55);
            font-size: 0.8rem;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="text-center">
                <div class="brand-logo">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h1 class="login-title">{{ setting('company_name', 'MyCron') }}</h1>
                <p class="login-subtitle">Masuk ke panel kontrol penjadwalan tugas otomatis</p>
            </div>

            @if ($errors->any())
                <div class="alert-modern mb-4">
                    <i class="bi bi-exclamation-circle-fill fs-5"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="/login">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Alamat Email</label>
                    <div class="input-group-modern">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-control"
                            placeholder="nama@domain.com" value="{{ old('email') }}" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group-modern">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control has-toggle"
                            placeholder="Masukkan password Anda" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Tampilkan password">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-login">
                        <span>Masuk ke Dashboard</span>
                        <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="login-footer">
            &copy; {{ date('Y') }} {{ setting('company_name', 'MyCron') }} &mdash; Sebelas Dua Belas Project
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>

</html>
