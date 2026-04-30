<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Seguimiento Comercial</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 460px; margin: 60px auto; padding: 0 14px; }
        label { display: block; margin-top: 10px; }
        input { width: 100%; padding: 8px; box-sizing: border-box; }
        .box { border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
        .error { background:#ffe9e9;border:1px solid #ffb4b4;padding:8px;margin-top:10px; }
    </style>
</head>
<body>
    <h1>Ingreso</h1>
    <div class="box">
        <form method="post" action="{{ route('login.submit') }}">
            @csrf
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label>Contrasena</label>
            <input type="password" name="password" required>

            <label style="margin-top:12px;">
                <input type="checkbox" name="remember" value="1" style="width:auto;"> Recordarme
            </label>
            <button type="submit" style="margin-top:12px;">Entrar</button>
        </form>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
    </div>
</body>
</html>
