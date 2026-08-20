<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — LeadsBoard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1fa97d;
            --on-primary: #ffffff;
            --primary-hover: #106647;
            --background: #faf9f2;
            --on-background: #1e2a22;
            --surface-low: #f4f1e6;
            --on-surface: #1e2a22;
            --on-surface-variant: #52584a;
            --outline: #cac5b0;
            --primary-container: #c9f1e1;
            --error: #c13f2c;
            --shadow-color: rgba(30, 42, 34, 0.06);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--background);
            font-family: 'Inter', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--on-background);
        }
        .login-card {
            background: var(--surface-low);
            border: 1px solid var(--outline);
            border-radius: 16px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px var(--shadow-color);
        }
        .login-brand {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            text-align: center;
            margin-bottom: 8px;
        }
        .login-subtitle {
            text-align: center;
            color: var(--on-surface-variant);
            font-size: 14px;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--on-surface-variant);
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--outline);
            border-radius: 8px;
            background: var(--background);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--on-surface);
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-container);
        }
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--on-surface-variant);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: var(--on-primary);
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-login:hover { background: var(--primary-hover); }
        .error-msg {
            background: #fce4ec;
            color: var(--error);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">LeadsBoard</div>
        <p class="login-subtitle">Sign in to your dashboard</p>

        @if ($errors->any())
            <div class="error-msg">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email', 'admin@leadsboard.local') }}" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="password" required>
            </div>

            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember" style="margin: 0;">Remember me</label>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
</body>
</html>
