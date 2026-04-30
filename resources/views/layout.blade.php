<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seguimiento Comercial</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1100px; margin: 20px auto; padding: 0 14px; }
        nav a { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .ok { background: #e8f7e8; border: 1px solid #b8e4b8; padding: 8px; margin: 10px 0; }
        .actions { display: flex; gap: 8px; }
        form.inline { display: inline; }
        label { display: block; margin-top: 8px; }
        input, textarea, select { width: 100%; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>
    <h1>Seguimiento Comercial</h1>
    @auth
        <nav>
            <a href="{{ route('base-asignada.index') }}">Base asignada</a>
            <a href="{{ route('clientes-potenciales.index') }}">Clientes potenciales</a>
            @if(auth()->user()->role === 'comercial')
                <a href="{{ route('base-asignada.cerradas') }}">Mis cerrados</a>
                <a href="{{ route('base-asignada.pendientes-comercial') }}">Pendientes por aprobar</a>
            @endif
            @if(auth()->user()->role === 'supervisor')
                <a href="{{ route('base-asignada.pendientes') }}">Gestiones pendientes por aprobar</a>
                <a href="{{ route('supervisor.comerciales') }}">Comerciales y gestion</a>
            @endif
        </nav>
        <p>
            Usuario: <strong>{{ auth()->user()->name }}</strong>
            ({{ auth()->user()->role }})
            <form method="post" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit">Cerrar sesion</button>
            </form>
        </p>
    @endauth
    @if (session('ok'))
        <div class="ok">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
        <div style="background:#ffe9e9;border:1px solid #ffb4b4;padding:8px;margin:10px 0;">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')
</body>
</html>
