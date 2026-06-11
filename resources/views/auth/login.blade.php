<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Seguimiento Comercial COOTRASENA</title>
    <style>
        :root {
            --green-900: #1f5b2b;
            --green-700: #2f7b3d;
            --green-500: #39a949;
            --green-100: #e8f7eb;
            --text: #1f2937;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: var(--text);
            display: grid;
            place-items: center;
            overflow: hidden;
            background: radial-gradient(circle at 20% 20%, #3b8d4d 0%, #2f5a34 40%, #214227 100%);
        }
        .bg-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(42px);
            opacity: 0.35;
            pointer-events: none;
            transform: translate3d(0,0,0);
        }
        .g1 { width: 320px; height: 320px; background: #7dff9d; top: -100px; left: -60px; }
        .g2 { width: 280px; height: 280px; background: #fff08a; bottom: -80px; right: -40px; }
        .g3 { width: 220px; height: 220px; background: #5ee3ff; top: 30%; right: 22%; }

        .login-shell {
            width: min(460px, calc(100% - 28px));
            perspective: 1200px;
        }
        .card {
            position: relative;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(255,255,255,0.65);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 20px 40px rgba(8, 31, 13, 0.28);
            backdrop-filter: blur(8px);
            transform-style: preserve-3d;
            transition: transform 0.2s ease;
        }
        .brand {
            text-align: center;
            margin-bottom: 8px;
        }
        .brand img {
            width: 210px;
            max-width: 100%;
            height: auto;
        }
        .brand-fallback {
            display: none;
            font-weight: 700;
            color: var(--green-900);
            background: var(--green-100);
            border: 1px solid #b7ddbd;
            border-radius: 10px;
            padding: 8px 12px;
        }
        h1 {
            margin: 10px 0 0;
            font-size: 1.55rem;
            text-align: center;
            letter-spacing: -0.02em;
            color: #134a21;
        }
        .sub {
            margin: 6px 0 18px;
            text-align: center;
            color: #456254;
            font-size: 0.95rem;
        }
        .field { margin-bottom: 12px; }
        label {
            display: block;
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: #274636;
        }
        input {
            width: 100%;
            border: 1px solid #cfe4d3;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 14px;
            background: #fafdff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.12s ease;
        }
        input:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(57, 169, 73, 0.18);
            outline: none;
            transform: translateY(-1px);
        }
        button {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 700;
            background: linear-gradient(135deg, #39a949, #2f8b3c);
            color: #fff;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.2s ease;
            box-shadow: 0 10px 22px rgba(47, 139, 60, 0.36);
        }
        button:hover { transform: translateY(-1px); }
        button:active { transform: translateY(1px); }
        .err {
            margin-top: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #f5b2b2;
            font-size: 14px;
            line-height: 1.35;
        }
        .err strong {
            display: block;
            margin-bottom: 2px;
        }
        .pulse {
            position: absolute;
            inset: -2px;
            border-radius: 20px;
            border: 2px solid rgba(57, 169, 73, 0.24);
            animation: pulse 3.2s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.35; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.01); }
        }
    </style>
</head>
<body>
    <div class="bg-glow g1"></div>
    <div class="bg-glow g2"></div>
    <div class="bg-glow g3"></div>

    <div class="login-shell">
        <form class="card" method="POST" action="{{ route('login.submit') }}" id="login-card">
            <div class="pulse"></div>
            <div class="brand">
                <img src="{{ asset('images/logo-cotrasena.png') }}" alt="COTRASENA" onerror="this.style.display='none';document.getElementById('brand-fallback').style.display='inline-block';">
                <span id="brand-fallback" class="brand-fallback">COOTRASENA</span>
            </div>
            @csrf
            <h1>Seguimiento comercial</h1>
            <p class="sub">Inicio de sesion</p>

            <div class="field">
                <label for="email">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="field">
                <label for="password">Contrasena</label>
                <input id="password" type="password" name="password" required>
            </div>

            <button type="submit">Ingresar</button>

            @if($errors->any())
                <div class="err">
                    <strong>No pudimos iniciar sesion</strong>
                    {{ $errors->first() }}
                </div>
            @endif
        </form>
    </div>

    <script>
        (function () {
            const card = document.getElementById('login-card');
            const shell = document.querySelector('.login-shell');
            const glows = Array.from(document.querySelectorAll('.bg-glow'));
            if (!card || !shell) return;

            shell.addEventListener('mousemove', (e) => {
                const rect = shell.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;
                const rotY = (x - 0.5) * 8;
                const rotX = (0.5 - y) * 8;
                card.style.transform = `rotateX(${rotX}deg) rotateY(${rotY}deg)`;
            });

            shell.addEventListener('mouseleave', () => {
                card.style.transform = 'rotateX(0deg) rotateY(0deg)';
            });

            let t = 0;
            setInterval(() => {
                t += 0.03;
                glows.forEach((g, i) => {
                    const ax = Math.sin(t + i) * 10;
                    const ay = Math.cos(t + i * 0.7) * 10;
                    g.style.transform = `translate(${ax}px, ${ay}px)`;
                });
            }, 40);
        })();
    </script>
</body>
</html>
