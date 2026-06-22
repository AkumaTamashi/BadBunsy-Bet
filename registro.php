<?php
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit();
}

$error    = '';
$jugadores = getJugadoresActivos(); // ← dinámico desde BD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = sanitize($_POST['nombre']   ?? '');
    $correo   = sanitize($_POST['correo']   ?? '');
    $password = $_POST['password']          ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';
    $jugador  = $_POST['jugador_asociado']  ?? '';

    if (empty($nombre) || empty($correo) || empty($password) || empty($jugador)) {
        $error = 'Completa todos los campos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Correo no válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!esJugadorValido($jugador)) {
        $error = 'Jugador asociado no válido.';
    } else {
        $db    = getDB();
        $check = $db->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $check->execute([$correo]);
        if ($check->fetch()) {
            $error = 'Este correo ya está registrado.';
        } else {
            // Leer saldo inicial desde configuración (si existe) o usar constante
            try {
                $saldoInicial = intval(
                    $db->query("SELECT valor FROM configuracion WHERE clave='saldo_inicial'")->fetchColumn()
                ) ?: SALDO_INICIAL;
            } catch (Exception $e) {
                $saldoInicial = SALDO_INICIAL;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare(
                "INSERT INTO usuarios (nombre, correo, password, saldo, rol, jugador_asociado) VALUES (?,?,?,?,?,?)"
            )->execute([$nombre, $correo, $hash, $saldoInicial, 'usuario', $jugador]);

            $newId = $db->lastInsertId();
            $db->prepare(
                "INSERT INTO movimientos (usuario_id, tipo, monto, descripcion) VALUES (?,?,?,?)"
            )->execute([$newId, 'deposito', $saldoInicial, 'Saldo inicial de bienvenida']);

            flashMessage('success', '¡Cuenta creada! Recibes ' . number_format($saldoInicial, 0, ',', '.') . ' BB de bienvenida.');
            header('Location: ' . SITE_URL . '/login.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Bad Bunsy Bet</title>
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

        <h1 class="auth-title">Crear Cuenta</h1>
        <p class="auth-subtitle">Únete a la plataforma privada del club</p>

        <?php if ($error): ?>
        <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
            <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($jugadores)): ?>
        <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
            <i class="fas fa-exclamation-circle"></i>
            No hay jugadores activos. Contacta al administrador.
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control"
                       placeholder="Tu nombre"
                       value="<?= sanitize($_POST['nombre'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control"
                       placeholder="tu@correo.com"
                       value="<?= sanitize($_POST['correo'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control"
                       placeholder="Mínimo 6 caracteres" required>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="confirm_password" class="form-control"
                       placeholder="Repite tu contraseña" required>
            </div>

            <div class="form-group">
                <label class="form-label">Tu jugador oficial del club</label>
                <select name="jugador_asociado" class="form-control" required
                        <?= empty($jugadores) ? 'disabled' : '' ?>>
                    <option value="">— Selecciona tu jugador —</option>
                    <?php foreach ($jugadores as $j): ?>
                    <option value="<?= sanitize($j) ?>"
                        <?= (($_POST['jugador_asociado'] ?? '') === $j) ? 'selected' : '' ?>>
                        <?= sanitize($j) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-text">
                    <i class="fas fa-info-circle"></i>
                    No podrás apostar en eventos relacionados con tu propio jugador.
                </p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block"
                    <?= empty($jugadores) ? 'disabled' : '' ?>>
                <i class="fas fa-user-plus"></i>
                Crear cuenta y recibir BB de bienvenida
            </button>
        </form>

        <div class="auth-link">
            ¿Ya tienes cuenta? <a href="<?= SITE_URL ?>/login.php">Inicia sesión</a>
        </div>
    </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>