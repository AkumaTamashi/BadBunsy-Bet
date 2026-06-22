<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$error = $success = '';

// ============================================================
// ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion  = sanitize($_POST['accion']  ?? '');
    $user_id = intval($_POST['user_id']   ?? 0);

    if ($user_id <= 0) { $error = 'Usuario inválido.'; }

    elseif ($accion === 'ajustar_saldo') {
        $monto = floatval($_POST['monto'] ?? 0);
        $tipo  = sanitize($_POST['tipo_ajuste'] ?? 'deposito');
        $nota  = sanitize($_POST['nota'] ?? 'Ajuste manual por admin');
        if ($monto <= 0) {
            $error = 'Monto inválido.';
        } else {
            if ($tipo === 'deposito') {
                $db->prepare("UPDATE usuarios SET saldo=saldo+? WHERE id=?")->execute([$monto, $user_id]);
            } else {
                // Verificar saldo suficiente
                $saldo = floatval($db->prepare("SELECT saldo FROM usuarios WHERE id=?")->execute([$user_id]) ? $db->query("SELECT saldo FROM usuarios WHERE id=$user_id")->fetchColumn() : 0);
                $s2 = $db->prepare("SELECT saldo FROM usuarios WHERE id=?"); $s2->execute([$user_id]);
                $saldo = floatval($s2->fetchColumn());
                if ($monto > $saldo) { $error = 'El usuario no tiene saldo suficiente.'; goto end_post; }
                $db->prepare("UPDATE usuarios SET saldo=saldo-? WHERE id=?")->execute([$monto, $user_id]);
            }
            $db->prepare("INSERT INTO movimientos (usuario_id,tipo,monto,descripcion) VALUES (?,?,?,?)")
               ->execute([$user_id, $tipo, $monto, $nota]);
            $success = "✓ Saldo ajustado correctamente.";
        }
    }

    elseif ($accion === 'cambiar_rol') {
        $nuevo_rol = sanitize($_POST['nuevo_rol'] ?? '');
        if (in_array($nuevo_rol, ['admin','usuario'])) {
            $db->prepare("UPDATE usuarios SET rol=? WHERE id=?")->execute([$nuevo_rol, $user_id]);
            $success = "✓ Rol actualizado.";
        }
    }

    elseif ($accion === 'toggle_activo') {
        $activo = intval($_POST['activo'] ?? 1);
        $db->prepare("UPDATE usuarios SET activo=? WHERE id=?")->execute([$activo, $user_id]);
        $success = "✓ Estado del usuario actualizado.";
    }

    elseif ($accion === 'cambiar_jugador') {
        $jugador = sanitize($_POST['jugador_asociado'] ?? '');
        if (esJugadorValido($jugador)) {
            $db->prepare("UPDATE usuarios SET jugador_asociado=? WHERE id=?")->execute([$jugador, $user_id]);
            $success = "✓ Jugador asociado actualizado.";
        } else { $error = 'Jugador no válido.'; }
    }

    elseif ($accion === 'reset_password') {
        $nueva = $_POST['nueva_password'] ?? '';
        if (strlen($nueva) < 6) { $error = 'La contraseña debe tener al menos 6 caracteres.'; }
        else {
            $db->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([password_hash($nueva, PASSWORD_DEFAULT), $user_id]);
            $success = "✓ Contraseña reseteada.";
        }
    }

    end_post:;
}

// ============================================================
// CARGAR USUARIOS
// ============================================================
$buscar   = sanitize($_GET['q']  ?? '');
$filtroRol = sanitize($_GET['rol'] ?? 'todos');

$sql = "SELECT u.*,
    (SELECT COUNT(*) FROM apuestas WHERE usuario_id=u.id) as total_apuestas,
    (SELECT COUNT(*) FROM apuestas WHERE usuario_id=u.id AND estado='ganada') as apuestas_ganadas,
    (SELECT COUNT(*) FROM apuestas WHERE usuario_id=u.id AND estado='pendiente') as apuestas_pend
    FROM usuarios u WHERE 1=1";
$params = [];

if ($buscar) {
    $sql .= " AND (u.nombre LIKE ? OR u.correo LIKE ? OR u.jugador_asociado LIKE ?)";
    $params = array_merge($params, ["%$buscar%","%$buscar%","%$buscar%"]);
}
if ($filtroRol !== 'todos') {
    $sql .= " AND u.rol = ?";
    $params[] = $filtroRol;
}
$sql .= " ORDER BY u.saldo DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$jugadores = getJugadoresActivos();

// Usuario seleccionado para editar
$user_sel = null;
$user_sel_id = intval($_GET['edit'] ?? 0);
if ($user_sel_id > 0) {
    $s = $db->prepare("SELECT * FROM usuarios WHERE id=?");
    $s->execute([$user_sel_id]);
    $user_sel = $s->fetch();
}

$pageTitle = 'Gestionar Usuarios';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-users"></i> Gestionar Usuarios</h1>
        <p class="page-subtitle"><?= count($usuarios) ?> usuarios encontrados</p>
    </div>

    <?php if ($error): ?>
    <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flash-message flash-success" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;flex:1;min-width:240px;">
            <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, correo o jugador…"
                   value="<?= sanitize($buscar) ?>" style="flex:1;">
            <?php if ($filtroRol !== 'todos'): ?>
            <input type="hidden" name="rol" value="<?= $filtroRol ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($buscar): ?>
            <a href="?rol=<?= $filtroRol ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
        <div style="display:flex;gap:6px;">
            <?php foreach (['todos','usuario','admin'] as $r): ?>
            <a href="?rol=<?= $r ?><?= $buscar?"&q=$buscar":'' ?>"
               class="btn btn-sm <?= $filtroRol===$r?'btn-primary':'btn-outline' ?>">
                <?= ucfirst($r) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid-2" style="align-items:start;gap:20px;">

        <!-- Tabla de usuarios -->
        <div class="card" style="<?= $user_sel ? '' : 'grid-column:1/-1' ?>">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Jugador</th>
                            <th>Saldo</th>
                            <th>Apuestas</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr <?= $user_sel && $user_sel['id']===$u['id'] ? 'style="background:var(--bg-hover);"' : '' ?>>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar" style="width:32px;height:32px;font-size:.7rem;border-radius:50%;">
                                        <?= strtoupper(substr($u['nombre'],0,2)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:.875rem;"><?= sanitize($u['nombre']) ?></div>
                                        <div style="font-size:.72rem;color:var(--text-muted);"><?= sanitize($u['correo']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-blue"><?= sanitize($u['jugador_asociado']) ?></span></td>
                            <td class="fw-bold text-green"><?= formatMoney($u['saldo']) ?> BB</td>
                            <td>
                                <span title="Total"><?= $u['total_apuestas'] ?></span>
                                <span style="color:var(--green);font-size:.75rem;"> ✓<?= $u['apuestas_ganadas'] ?></span>
                                <?php if ($u['apuestas_pend']>0): ?>
                                <span style="color:var(--gold);font-size:.75rem;"> ⏳<?= $u['apuestas_pend'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $u['rol']==='admin'?'badge-gold':'badge-muted' ?>">
                                    <?= $u['rol'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['activo']?'badge-green':'badge-red' ?>">
                                    <?= $u['activo']?'Activo':'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?edit=<?= $u['id'] ?><?= $buscar?"&q=$buscar":'' ?>&rol=<?= $filtroRol ?>"
                                   class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($usuarios)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">Sin resultados</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Panel de edición -->
        <?php if ($user_sel): ?>
        <div>
            <div class="card" style="position:sticky;top:80px;">
                <div class="card-header">
                    <div>
                        <span class="card-title"><i class="fas fa-user-edit"></i> Editando usuario</span>
                        <div style="font-size:.77rem;color:var(--text-muted);margin-top:2px;"><?= sanitize($user_sel['nombre']) ?></div>
                    </div>
                    <a href="?rol=<?= $filtroRol ?>" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
                </div>
                <div class="card-body" style="padding:16px;">

                    <!-- Saldo actual -->
                    <div style="background:rgba(34,197,94,.08);border:1px solid var(--green);border-radius:8px;padding:14px;margin-bottom:16px;text-align:center;">
                        <div style="font-size:.78rem;color:var(--text-muted);">Saldo actual</div>
                        <div style="font-size:1.6rem;font-weight:800;color:var(--green);"><?= formatMoney($user_sel['saldo']) ?> BB</div>
                    </div>

                    <!-- Ajustar saldo -->
                    <details style="margin-bottom:12px;">
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;padding:8px 0;color:var(--text-primary);">
                            <i class="fas fa-coins" style="color:var(--gold)"></i> Ajustar saldo
                        </summary>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="accion"  value="ajustar_saldo">
                            <input type="hidden" name="user_id" value="<?= $user_sel['id'] ?>">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                                <div>
                                    <label class="form-label" style="font-size:.75rem;">Tipo</label>
                                    <select name="tipo_ajuste" class="form-control">
                                        <option value="deposito">➕ Agregar</option>
                                        <option value="retiro">➖ Quitar</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size:.75rem;">Monto (BB)</label>
                                    <input type="number" name="monto" class="form-control" min="1" placeholder="BB" required>
                                </div>
                            </div>
                            <div style="margin-bottom:8px;">
                                <label class="form-label" style="font-size:.75rem;">Nota</label>
                                <input type="text" name="nota" class="form-control" placeholder="Razón del ajuste">
                            </div>
                            <button type="submit" class="btn btn-gold btn-sm btn-block">
                                <i class="fas fa-coins"></i> Aplicar ajuste
                            </button>
                        </form>
                    </details>

                    <!-- Cambiar jugador -->
                    <details style="margin-bottom:12px;">
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;padding:8px 0;color:var(--text-primary);">
                            <i class="fas fa-user-tag" style="color:var(--blue)"></i> Cambiar jugador asociado
                        </summary>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="accion"  value="cambiar_jugador">
                            <input type="hidden" name="user_id" value="<?= $user_sel['id'] ?>">
                            <select name="jugador_asociado" class="form-control" style="margin-bottom:8px;">
                                <?php foreach ($jugadores as $j): ?>
                                <option value="<?= sanitize($j) ?>" <?= $user_sel['jugador_asociado']===$j?'selected':'' ?>>
                                    <?= sanitize($j) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                        </form>
                    </details>

                    <!-- Cambiar rol -->
                    <details style="margin-bottom:12px;">
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;padding:8px 0;color:var(--text-primary);">
                            <i class="fas fa-shield-alt" style="color:var(--gold)"></i> Cambiar rol
                        </summary>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="accion"  value="cambiar_rol">
                            <input type="hidden" name="user_id" value="<?= $user_sel['id'] ?>">
                            <select name="nuevo_rol" class="form-control" style="margin-bottom:8px;">
                                <option value="usuario" <?= $user_sel['rol']==='usuario'?'selected':'' ?>>Usuario</option>
                                <option value="admin"   <?= $user_sel['rol']==='admin'?'selected':'' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-gold btn-sm btn-block"
                                    onclick="return confirm('¿Cambiar el rol?')">
                                <i class="fas fa-save"></i> Guardar rol
                            </button>
                        </form>
                    </details>

                    <!-- Reset password -->
                    <details style="margin-bottom:12px;">
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;padding:8px 0;color:var(--text-primary);">
                            <i class="fas fa-key" style="color:var(--red)"></i> Resetear contraseña
                        </summary>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="accion"  value="reset_password">
                            <input type="hidden" name="user_id" value="<?= $user_sel['id'] ?>">
                            <input type="text" name="nueva_password" class="form-control"
                                   placeholder="Nueva contraseña (mín. 6 chars)"
                                   style="margin-bottom:8px;" required>
                            <button type="submit" class="btn btn-danger btn-sm btn-block"
                                    onclick="return confirm('¿Resetear la contraseña?')">
                                <i class="fas fa-key"></i> Resetear
                            </button>
                        </form>
                    </details>

                    <!-- Activar / desactivar -->
                    <details>
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;padding:8px 0;color:var(--text-primary);">
                            <i class="fas fa-power-off" style="color:var(--text-muted)"></i>
                            <?= $user_sel['activo'] ? 'Desactivar cuenta' : 'Activar cuenta' ?>
                        </summary>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="accion"  value="toggle_activo">
                            <input type="hidden" name="user_id" value="<?= $user_sel['id'] ?>">
                            <input type="hidden" name="activo"  value="<?= $user_sel['activo'] ? 0 : 1 ?>">
                            <button type="submit"
                                    class="btn <?= $user_sel['activo']?'btn-danger':'btn-primary' ?> btn-sm btn-block"
                                    onclick="return confirm('¿Confirmar?')">
                                <?= $user_sel['activo']
                                    ? '<i class="fas fa-ban"></i> Desactivar cuenta'
                                    : '<i class="fas fa-check"></i> Activar cuenta' ?>
                            </button>
                        </form>
                    </details>

                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>