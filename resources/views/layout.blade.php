<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seguimiento Comercial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f7fb;
            --panel: #ffffff;
            --panel-soft: #f7fafc;
            --text: #10263f;
            --muted: #5e738a;
            --line: #d5e1eb;
            --primary: #0e7490;
            --primary-strong: #0c5f76;
            --ok-bg: #e7f8ef;
            --ok-line: #9ad6b3;
            --danger-bg: #fff1f1;
            --danger-line: #f2b2b2;
            --radius: 14px;
            --shadow: 0 10px 30px rgba(5, 35, 58, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            overflow-x: hidden;
            background:
                radial-gradient(circle at 0 0, #d9f1ff 0%, transparent 35%),
                radial-gradient(circle at 100% 0, #e1f5ec 0%, transparent 35%),
                var(--bg);
        }
        .bg-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(42px);
            opacity: 0.25;
            pointer-events: none;
            z-index: 0;
        }
        .g1 { width: 280px; height: 280px; background: #7dff9d; top: -90px; left: -30px; }
        .g2 { width: 230px; height: 230px; background: #ffd47b; bottom: -60px; right: -30px; }
        .g3 { width: 180px; height: 180px; background: #79d9ff; top: 35%; right: 28%; }
        .app-shell {
            max-width: 1250px;
            margin: 22px auto;
            padding: 0 16px;
            position: relative;
            z-index: 2;
        }
        .app-header {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: calc(var(--radius) + 4px);
            box-shadow: var(--shadow);
            padding: 18px;
            margin-bottom: 18px;
            transition: transform 0.18s ease, box-shadow 0.22s ease;
        }
        .app-header:hover { transform: translateY(-1px); box-shadow: 0 16px 34px rgba(5, 35, 58, 0.12); }
        .brand-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .brand-logo {
            width: 170px;
            max-width: 100%;
            height: auto;
            display: block;
        }
        h1 {
            margin: 0;
            font-size: 1.55rem;
            letter-spacing: -0.02em;
        }
        h2, h3 {
            letter-spacing: -0.015em;
            margin-top: 20px;
        }
        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        nav a {
            color: var(--text);
            text-decoration: none;
            background: var(--panel-soft);
            border: 1px solid var(--line);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.92rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        nav a.active {
            background: #dff3f8;
            border-color: #8fd0e2;
            color: #0a4f68;
        }
        nav a:hover {
            background: #e9f8fd;
            border-color: #b9e5f4;
        }
        .user-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--muted);
            font-size: 0.95rem;
            margin: 8px 0 0 0;
        }
        .user-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .user-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .clock-badge {
            background: #edf5fb;
            border: 1px solid #cde1ef;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.85rem;
            color: #244660;
            font-weight: 700;
        }
        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px;
            transition: transform 0.2s ease, box-shadow 0.25s ease;
            transform-style: preserve-3d;
        }
        p, li, td, th, label, input, select, textarea, button, a, small {
            line-height: 1.45;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 12px;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            overflow: hidden;
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        table thead tr {
            background: #edf5fb;
        }
        th, td {
            border-bottom: 1px solid #e3edf5;
            padding: 11px 12px;
            text-align: left;
            font-size: 0.94rem;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tbody tr { transition: background-color 0.16s ease; }
        tbody tr:hover { background: #f7fcff; }
        .ok {
            background: var(--ok-bg);
            border: 1px solid var(--ok-line);
            border-radius: 10px;
            padding: 10px 12px;
            margin: 10px 0 12px;
        }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        form.inline { display: inline; }
        form { margin: 8px 0 12px; }
        label { display: block; margin-top: 8px; font-weight: 600; color: #244660; }
        input, textarea, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #c8d8e6;
            background: #fbfdff;
            color: var(--text);
            font-family: inherit;
            font-size: 0.95rem;
        }
        input:focus, textarea:focus, select:focus {
            outline: 2px solid rgba(14, 116, 144, 0.15);
            border-color: var(--primary);
        }
        input[type="checkbox"] { width: auto; }
        button, .btn-link {
            appearance: none;
            border: 1px solid var(--primary);
            background: var(--primary);
            color: #fff;
            border-radius: 10px;
            padding: 9px 13px;
            font-family: inherit;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        button:hover, .btn-link:hover {
            background: var(--primary-strong);
            border-color: var(--primary-strong);
            transform: translateY(-1px);
        }
        button:active, .btn-link:active { transform: translateY(1px); }
        a {
            color: #0a5f80;
        }
        .danger {
            background: var(--danger-bg);
            border: 1px solid var(--danger-line);
            padding: 10px 12px;
            border-radius: 10px;
            margin: 10px 0 12px;
        }
        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            list-style: none;
            padding: 0;
            margin: 16px 0 0;
        }
        .pagination .page-item {
            list-style: none;
        }
        .pagination .page-link,
        .pagination .page-item span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 9px;
            color: #345773;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .pagination .page-item.active span {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .pagination .page-item.disabled span {
            opacity: 0.45;
        }
        .inline-filters {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .inline-filters .field {
            min-width: 120px;
        }
        .inline-filters input,
        .inline-filters select {
            width: auto;
            min-width: 110px;
        }
        @media (max-width: 860px) {
            .user-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .app-shell {
                padding: 0 10px;
            }
            .app-header, .panel {
                padding: 14px;
            }
            .inline-filters input,
            .inline-filters select {
                width: 100%;
                min-width: 0;
            }
            .inline-filters .field {
                width: 100%;
            }
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(4, 27, 46, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 14px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-card {
            width: min(760px, 100%);
            max-height: 88vh;
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            padding: 16px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .modal-close {
            border: 1px solid #c8d8e6;
            background: #f7fbff;
            color: #23455f;
        }
        .notify-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #f2b3b3;
            background: #fff1f1;
            color: #aa2020;
            padding: 8px 11px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.9rem;
        }
        .notify-link:hover {
            background: #ffe6e6;
            color: #8f1717;
        }
        .notify-count {
            min-width: 20px;
            height: 20px;
            border-radius: 999px;
            background: #d91f1f;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 800;
            line-height: 1;
            padding: 0 6px;
        }
    </style>
</head>
<body>
    @php
        if (auth()->check()) {
            $gestionesVencidas = \App\Models\Gestion::query()
                ->join('base_asignadas', 'base_asignadas.id', '=', 'gestions.base_asignada_id')
                ->where('base_asignadas.asesor_id', auth()->id())
                ->whereNotNull('gestions.proxima_gestion_at')
                ->where('gestions.proxima_gestion_at', '<=', now())
                ->select('gestions.id', 'gestions.proxima_gestion_at', 'base_asignadas.nombre', 'base_asignadas.cedula')
                ->get();

            foreach ($gestionesVencidas as $gVencida) {
                \App\Models\AppNotification::firstOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'type' => 'proxima_gestion_vencida',
                        'related_id' => $gVencida->id,
                        'related_type' => \App\Models\Gestion::class,
                    ],
                    [
                        'title' => 'Proxima gestion pendiente',
                        'message' => 'Ya puedes gestionar ' . ($gVencida->nombre ?: 'registro') . ' (' . ($gVencida->cedula ?: 'sin cedula') . ').',
                        'event_at' => $gVencida->proxima_gestion_at,
                    ]
                );
            }
        }
    @endphp
    @php($unreadNotifications = auth()->check() ? \App\Models\AppNotification::where('user_id', auth()->id())->whereNull('read_at')->count() : 0)
    @php($headerNotifications = auth()->check() ? \App\Models\AppNotification::where('user_id', auth()->id())->latest('created_at')->limit(12)->get() : collect())
    <div class="bg-glow g1"></div>
    <div class="bg-glow g2"></div>
    <div class="bg-glow g3"></div>
    <div class="app-shell">
        <header class="app-header">
            <div class="brand-row">
                <img class="brand-logo" src="{{ asset('images/logo-cotrasena.png') }}" alt="COTRASENA" onerror="this.style.display='none';">
                <h1>Seguimiento Comercial</h1>
            </div>
            @auth
                <nav>
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                    <a class="{{ request()->routeIs('base-asignada.*') && !request()->routeIs('base-asignada.historico-cedula') ? 'active' : '' }}" href="{{ route('base-asignada.index') }}">Base asignada</a>
                    <a class="{{ request()->routeIs('base-asignada.historico-cedula') ? 'active' : '' }}" href="{{ route('base-asignada.historico-cedula') }}">Historico por cedula</a>
                    <a class="{{ request()->routeIs('clientes-potenciales.*') ? 'active' : '' }}" href="{{ route('clientes-potenciales.index') }}">Clientes potenciales</a>
                    @if(auth()->user()->role === 'comercial')
                        <a class="{{ request()->routeIs('base-asignada.cerradas') ? 'active' : '' }}" href="{{ route('base-asignada.cerradas') }}">Mis cerrados</a>
                        <a class="{{ request()->routeIs('base-asignada.pendientes-comercial') ? 'active' : '' }}" href="{{ route('base-asignada.pendientes-comercial') }}">Pendientes por aprobar</a>
                    @endif
                    @if(auth()->user()->role === 'supervisor')
                        <a class="{{ request()->routeIs('base-asignada.pendientes*') ? 'active' : '' }}" href="{{ route('base-asignada.pendientes') }}">Gestiones pendientes por aprobar</a>
                        <a class="{{ request()->routeIs('supervisor.comerciales*') ? 'active' : '' }}" href="{{ route('supervisor.comerciales') }}">Comerciales y gestion</a>
                    @endif
                </nav>
                <div class="user-row">
                    <div class="user-meta">
                        Usuario: <strong>{{ auth()->user()->name }}</strong>
                        ({{ auth()->user()->role }})
                        <span id="clock-badge" class="clock-badge" data-now="{{ now()->format('Y-m-d H:i:s') }}"></span>
                    </div>
                    <div class="user-actions">
                        <button type="button" class="notify-link" id="open-notifications" title="Notificaciones">
                            <span aria-hidden="true">&#128276;</span>
                            <span>Notificaciones</span>
                            <span class="notify-count">{{ $unreadNotifications }}</span>
                        </button>
                        <form method="post" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit">Cerrar sesion</button>
                        </form>
                    </div>
                </div>
            @endauth
        </header>
        <main class="panel">
            @if (session('ok'))
                <div class="ok">{{ session('ok') }}</div>
            @endif
            @if ($errors->any())
                <div class="danger">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    <div class="modal-backdrop" id="notifications-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 style="margin:0;">Notificaciones</h3>
                <button type="button" class="modal-close" id="close-notifications">Cerrar</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Titulo</th>
                        <th>Mensaje</th>
                        <th>Estado</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($headerNotifications as $n)
                        <tr>
                            <td>{{ $n->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $n->title }}</td>
                            <td>{{ $n->message }}</td>
                            <td>{{ $n->read_at ? 'Leida' : 'Pendiente' }}</td>
                            <td>
                                @if(!$n->read_at)
                                    <form method="post" action="{{ route('notifications.read', $n->id) }}" class="inline">
                                        @csrf
                                        <button type="submit">Marcar leida</button>
                                    </form>
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No tienes notificaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p style="margin-top:10px;"><a href="{{ route('notifications.index') }}">Ver historial completo</a></p>
        </div>
    </div>
    <script>
        (function () {
            const el = document.getElementById('clock-badge');
            if (!el) return;
            const seed = el.dataset.now ? new Date(el.dataset.now.replace(' ', 'T')) : new Date();
            if (Number.isNaN(seed.getTime())) return;
            let current = seed;
            const fmt = new Intl.DateTimeFormat('es-CO', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
            const tick = () => {
                el.textContent = fmt.format(current);
                current = new Date(current.getTime() + 1000);
            };
            tick();
            setInterval(tick, 1000);
        })();
        (function () {
            const panel = document.querySelector('main.panel');
            const shell = document.querySelector('.app-shell');
            const glows = Array.from(document.querySelectorAll('.bg-glow'));
            if (panel && shell) {
                shell.addEventListener('mousemove', (e) => {
                    const rect = shell.getBoundingClientRect();
                    const x = (e.clientX - rect.left) / rect.width;
                    const y = (e.clientY - rect.top) / rect.height;
                    const rotY = (x - 0.5) * 2.2;
                    const rotX = (0.5 - y) * 2.2;
                    panel.style.transform = `rotateX(${rotX}deg) rotateY(${rotY}deg)`;
                });
                shell.addEventListener('mouseleave', () => {
                    panel.style.transform = 'rotateX(0deg) rotateY(0deg)';
                });
            }
            if (glows.length > 0) {
                let t = 0;
                setInterval(() => {
                    t += 0.03;
                    glows.forEach((g, i) => {
                        const ax = Math.sin(t + i) * 8;
                        const ay = Math.cos(t + i * 0.8) * 8;
                        g.style.transform = `translate(${ax}px, ${ay}px)`;
                    });
                }, 45);
            }
        })();
        (function () {
            const modal = document.getElementById('notifications-modal');
            const openBtn = document.getElementById('open-notifications');
            const closeBtn = document.getElementById('close-notifications');
            if (!modal || !openBtn || !closeBtn) return;

            const open = () => modal.classList.add('open');
            const close = () => modal.classList.remove('open');

            openBtn.addEventListener('click', open);
            closeBtn.addEventListener('click', close);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) close();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close();
            });
        })();
    </script>
</body>
</html>
