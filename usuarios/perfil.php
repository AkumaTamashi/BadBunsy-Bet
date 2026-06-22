<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($nombre)) {
        $error = 'El nombre no puede estar vacío.';
    } else {
        $update = "UPDATE usuarios SET nombre = ? WHERE id = ?";
        $params = [$nombre, $user['id']];

        if (!empty($password)) {
            if (strlen($password) < 6) {
                $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } elseif ($password !== $confirm) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $update = "UPDATE usuarios SET nombre = ?, password = ? WHERE id = ?";
                $params = [$nombre, password_hash($password, PASSWORD_DEFAULT), $user['id']];
            }
        }

        if (!$error) {
            $db->prepare($update)->execute($params);
            $_SESSION['nombre'] = $nombre;
            $success = 'Perfil actualizado correctamente.';
            $user = getCurrentUser();
        }
    }
}

$pageTitle = 'Mi Perfil';
include __DIR__ . '/../includes/header.php';
?>

<div class="container" style="max-width:700px;">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-user"></i> Mi Perfil</h1>
    </div>

    <?php if ($error): ?>
    <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flash-message flash-success" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-check-circle"></i> <?= sanitize($success) ?>
    </div>
    <?php endif; ?>

    <div class="card mb-2">
        <div class="card-header"><span class="card-title"><i class="fas fa-id-card"></i> Información de cuenta</span></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                <div class="user-avatar" style="width:64px;height:64px;font-size:1.4rem;border-radius:50%;">
                    <?= strtoupper(substr($user['nombre'], 0, 2)) ?>
                </div>
                <div>
                    <div style="font-size:1.2rem;font-weight:700;"><?= sanitize($user['nombre']) ?></div>
                    <div style="color:var(--text-muted);font-size:0.85rem;"><?= sanitize($user['correo']) ?></div>
                    <div style="margin-top:4px;">
                        <span class="badge badge-green"><?= $user['jugador_asociado'] ?></span>
                        <span class="badge badge-blue" style="margin-left:4px;"><?= ucfirst($user['rol']) ?></span>
                    </div>
                </div>
            </div>

            <div style="padding:16px;background:var(--bg-hover);border-radius:8px;margin-bottom:20px;">
                <div style="font-size:0.8rem;color:var(--text-muted);">Saldo actual</div>
                <div style="font-size:2rem;font-weight:800;color:var(--green);"><?= formatMoney($user['saldo']) ?> BB</div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" value="<?= sanitize($user['nombre']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nueva contraseña <span style="color:var(--text-muted);font-weight:400;">(dejar vacío para no cambiar)</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Nueva contraseña">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirmar nueva contraseña">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>