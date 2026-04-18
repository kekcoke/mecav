<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mecav</title>
    @vite(['resources/css/app.css'])
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { width: 320px; }
        input { margin-bottom: 1rem; }
        button { width: 100%; padding: 0.75rem; border: none; cursor: pointer; }
        .error { color: var(--accent-red); margin-bottom: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body class="dashboard-layout">
    <div class="login-card card">
        <h1>Mecav Login</h1>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/login" id="loginForm">
            @csrf
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required value="{{ old('email') }}">
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        // We let the form submit normally to create the session, 
        // but we'll capture the token if the AuthController returns it.
        // For now, the AuthController needs to be updated to return a token on login.
    });
    </script>
</body>
</html>
