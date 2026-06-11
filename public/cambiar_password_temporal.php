<?php

$tokenEsperado = 'cambio-seguro-2026-06-10';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!hash_equals($tokenEsperado, (string) $token)) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$mensaje = null;
$error = null;

try {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $bootstrap = __DIR__ . '/../bootstrap/app.php';

    if (!file_exists($autoload) || !file_exists($bootstrap)) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        $bootstrap = dirname(__DIR__) . '/bootstrap/app.php';
    }

    if (!file_exists($autoload)) {
        throw new RuntimeException('No se encontro vendor/autoload.php. Verifica que el archivo este dentro de la carpeta public del proyecto Laravel.');
    }

    if (!file_exists($bootstrap)) {
        throw new RuntimeException('No se encontro bootstrap/app.php. Verifica la ubicacion del archivo temporal.');
    }

    require $autoload;
    $app = require $bootstrap;
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error cargando Laravel: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmacion = (string) ($_POST['password_confirmation'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo valido.';
    } elseif (strlen($password) < 6) {
        $error = 'La nueva contrasena debe tener minimo 6 caracteres.';
    } elseif ($password !== $confirmacion) {
        $error = 'La confirmacion no coincide.';
    } else {
        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            $error = 'No existe un usuario con ese correo.';
        } else {
            $user->password = \Illuminate\Support\Facades\Hash::make($password);
            $user->save();
            $mensaje = "Contrasena actualizada para {$user->email}. Borra este archivo inmediatamente.";
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar contrasena temporal</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f3f7f4; color:#1f2937; margin:0; min-height:100vh; display:grid; place-items:center; }
        .card { width:min(420px, calc(100% - 28px)); background:#fff; border:1px solid #dbe7df; border-radius:14px; padding:22px; box-shadow:0 18px 35px rgba(0,0,0,.12); }
        h1 { margin:0 0 12px; color:#174c28; font-size:22px; }
        label { display:block; margin:12px 0 6px; font-weight:700; }
        input { width:100%; box-sizing:border-box; padding:11px; border:1px solid #cbd8cf; border-radius:9px; }
        button { margin-top:16px; width:100%; border:0; border-radius:9px; padding:12px; background:#23723a; color:#fff; font-weight:700; cursor:pointer; }
        .ok { background:#dcfce7; border:1px solid #86efac; color:#166534; padding:10px; border-radius:8px; margin-bottom:12px; }
        .err { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px; border-radius:8px; margin-bottom:12px; }
        .warn { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; padding:10px; border-radius:8px; margin-top:14px; font-size:14px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Cambiar contrasena</h1>
        <?php if ($mensaje): ?>
            <div class="ok"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($tokenEsperado, ENT_QUOTES, 'UTF-8') ?>">
            <label>Correo del usuario</label>
            <input type="email" name="email" required>
            <label>Nueva contrasena</label>
            <input type="password" name="password" required minlength="6">
            <label>Confirmar contrasena</label>
            <input type="password" name="password_confirmation" required minlength="6">
            <button type="submit">Actualizar contrasena</button>
        </form>
        <div class="warn">
            Despues de usarlo, borra este archivo del hosting: <strong>public/cambiar_password_temporal.php</strong>
        </div>
    </div>
</body>
</html>
