```php
<?php

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getCurrentUser();

if ($user['rol'] !== 'admin') {

    flashMessage('error', 'Acceso denegado.');

    header('Location: ' . SITE_URL);

    exit();
}

$db = getDB();

// =====================================================
// ELIMINAR USUARIO
// =====================================================

if (isset($_GET['eliminar'])) {

    $idEliminar = intval($_GET['eliminar']);

    if ($idEliminar !== $user['id']) {

        $stmt = $db->prepare("
            DELETE FROM usuarios
            WHERE id = ?
        ");

        $stmt->execute([$idEliminar]);

        flashMessage('success', 'Usuario eliminado.');

    } else {

        flashMessage('error', 'No puedes eliminarte.');

    }

    header('Location: usuarios.php');

    exit();
}

// =====================================================
// ACTUALIZAR USUARIO
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id']);

    $saldo = floatval($_POST['saldo']);

    $rol = sanitize($_POST['rol']);

    $jugador = sanitize($_POST['jugador_asociado']);

    $stmt = $db->prepare("
        UPDATE usuarios
        SET
            saldo = ?,
            rol = ?,
            jugador_asociado = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $saldo,
        $rol,
        $jugador,
        $id
    ]);

    flashMessage('success', 'Usuario actualizado.');

    header('Location: usuarios.php');

    exit();
}

// =====================================================
// OBTENER USUARIOS
// =====================================================

$stmt = $db->query("
    SELECT *
    FROM usuarios
    ORDER BY id DESC
");

$usuarios = $stmt->fetchAll();

$pageTitle = 'Gestión de Usuarios';

include __DIR__ . '/../includes/header.php';

?>

<div class="container">

    <div class="page-header">

        <h1 class="page-title">

            <i class="fas fa-users-cog"></i>

            Gestión de Usuarios

        </h1>

    </div>

    <div class="card">

        <div class="card-body">

            <div style="overflow-x:auto;">

                <table class="table">

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Usuario</th>

                            <th>Jugador</th>

                            <th>Saldo</th>

                            <th>Rol</th>

                            <th>Acciones</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($usuarios as $u): ?>

                            <tr>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $u['id'] ?>"
                                    >

                                    <td>

                                        #<?= $u['id'] ?>

                                    </td>

                                    <td>

                                        <?= sanitize($u['nombre']) ?>

                                    </td>

                                    <td>

                                        <select
                                            name="jugador_asociado"
                                            class="form-control"
                                        >

                                            <option value="ninguno">

                                                Ninguno

                                            </option>

                                            <?php foreach (JUGADORES as $jugador): ?>

                                                <option
                                                    value="<?= $jugador ?>"
                                                    <?= $u['jugador_asociado'] === $jugador ? 'selected' : '' ?>
                                                >

                                                    <?= $jugador ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            name="saldo"
                                            value="<?= $u['saldo'] ?>"
                                            class="form-control"
                                        >

                                    </td>

                                    <td>

                                        <select
                                            name="rol"
                                            class="form-control"
                                        >

                                            <option
                                                value="usuario"
                                                <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>
                                            >

                                                Usuario

                                            </option>

                                            <option
                                                value="admin"
                                                <?= $u['rol'] === 'admin' ? 'selected' : '' ?>
                                            >

                                                Admin

                                            </option>

                                        </select>

                                    </td>

                                    <td>

                                        <div
                                            style="
                                                display:flex;
                                                gap:8px;
                                            "
                                        >

                                            <button
                                                type="submit"
                                                class="btn btn-primary btn-sm"
                                            >

                                                <i class="fas fa-save"></i>

                                            </button>

                                            <?php if ($u['id'] != $user['id']): ?>

                                                <a
                                                    href="?eliminar=<?= $u['id'] ?>"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('¿Eliminar usuario?')"
                                                >

                                                    <i class="fas fa-trash"></i>

                                                </a>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </form>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
```
