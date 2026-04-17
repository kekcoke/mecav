<!DOCTYPE html>
<html>
<head>
    <title>Login - Mecav</title>
    <style>
        body { font-family: system-ui; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
        .login-card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 320px; }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        input { width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #2563eb; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Mecav Login</h1>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/login">
            @csrf
            <input type="email" name="email" placeholder="Email" required value="{{ old('email') }}">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>
