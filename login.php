<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = sanitize($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $error = 'Completa todos los campos.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1");
        $stmt->execute([$correo]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['nombre'] = $user['nombre'];
            flashMessage('success', '¡Bienvenido de vuelta, ' . $user['nombre'] . '!');
            header('Location: ' . SITE_URL . '/index.php');
            exit();
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

$pageTitle = 'Iniciar Sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Bad Bunsy Bet</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="brand-icon">⚽</span>
            <div class="brand-text">Bad<span class="brand-accent">Bunsy</span>Bet</div>
        </div>

        <h1 class="auth-title">Iniciar Sesión</h1>
        <p class="auth-subtitle">Bienvenido al club privado de apuestas FC26</p>

        <?php if ($error): ?>
        <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
            <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control" placeholder="tu@correo.com" 
                       value="<?= sanitize($_POST['correo'] ?? '') ?>" required autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-sign-in-alt"></i> Ingresar
            </button>
        </form>

        <div class="auth-link">
            ¿No tienes cuenta? <a href="<?= SITE_URL ?>/registro.php">Regístrate aquí</a>
        </div>
        <div style="text-align:center;margin-top:16px;font-size:0.75rem;color:var(--text-muted);">
            <i class="fas fa-info-circle"></i> Wazaaaaaa
        </div>
    </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>