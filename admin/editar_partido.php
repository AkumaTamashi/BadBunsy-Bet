<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db        = getDB();
$id        = intval($_GET['id'] ?? 0);
$jugadores = getJugadoresActivos(); // ← dinámico

if ($id <= 0) {
    flashMessage('error', 'Partido no válido.');
    header('Location: ' . SITE_URL . '/admin/partidos.php'); exit();
}

$partido = $db->prepare("SELECT * FROM partidos WHERE id = ?");
$partido->execute([$id]);
$partido = $partido->fetch();
if (!$partido) {
    flashMessage('error', 'Partido no encontrado.');
    header('Location: ' . SITE_URL . '/admin/partidos.php'); exit();
}

$eventos = $db->prepare("SELECT * FROM eventos WHERE partido_id = ? ORDER BY id ASC");
$eventos->execute([$id]);
$eventos = $eventos->fetchAll();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = sanitize($_POST['accion'] ?? '');

    if ($accion === 'guardar_partido') {
        $rival       = sanitize($_POST['rival']       ?? '');
        $fecha       = $_POST['fecha']                ?? '';
        $hora        = $_POST['hora']                 ?? '20:00';
        $descripcion = sanitize($_POST['descripcion'] ?? '');
        $estado      = sanitize($_POST['estado']      ?? 'abierto');
        if (empty($rival) || empty($fecha)) {
            $error = 'El rival y la fecha son obligatorios.';
        } else {
            $db->prepare("UPDATE partidos SET rival=?,fecha=?,hora=?,descripcion=?,estado=? WHERE id=?")
               ->execute([$rival, $fecha, $hora, $descripcion, $estado, $id]);
            $success = 'Partido actualizado.';
            $partido = $db->prepare("SELECT * FROM partidos WHERE id=?");
            $partido->execute([$id]); $partido = $partido->fetch();
        }
    }

    elseif ($accion === 'agregar_evento') {
        $desc    = sanitize($_POST['ev_desc']    ?? '');
        $cuota   = floatval($_POST['ev_cuota']   ?? 0);
        $jugador = sanitize($_POST['ev_jugador'] ?? 'ninguno');
        if (empty($desc) || $cuota <= 1.0) {
            $error = 'Descripción requerida y cuota debe ser mayor a 1.00.';
        } else {
            $db->prepare("INSERT INTO eventos (partido_id,descripcion,cuota,jugador_relacionado) VALUES (?,?,?,?)")
               ->execute([$id, $desc, $cuota, $jugador]);
            $success = "Mercado \"$desc\" agregado.";
        }
        $eventos = $db->prepare("SELECT * FROM eventos WHERE partido_id=? ORDER BY id ASC");
        $eventos->execute([$id]); $eventos = $eventos->fetchAll();
    }

    elseif ($accion === 'editar_evento') {
        $ev_id   = intval($_POST['ev_id']          ?? 0);
        $desc    = sanitize($_POST['ev_desc_edit'] ?? '');
        $cuota   = floatval($_POST['ev_cuota_edit']?? 0);
        $jugador = sanitize($_POST['ev_jugador_edit']?? 'ninguno');
        if ($ev_id > 0 && !empty($desc) && $cuota > 1.0) {
            $db->prepare("UPDATE eventos SET descripcion=?,cuota=?,jugador_relacionado=? WHERE id=? AND partido_id=?")
               ->execute([$desc, $cuota, $jugador, $ev_id, $id]);
            $success = 'Mercado actualizado.';
        } else { $error = 'Datos inválidos.'; }
        $eventos = $db->prepare("SELECT * FROM eventos WHERE partido_id=? ORDER BY id ASC");
        $eventos->execute([$id]); $eventos = $eventos->fetchAll();
    }

    elseif ($accion === 'eliminar_evento') {
        $ev_id = intval($_POST['ev_id'] ?? 0);
        if ($ev_id > 0) {
            $tiene = $db->prepare("SELECT COUNT(*) FROM apuesta_detalle WHERE evento_id=?");
            $tiene->execute([$ev_id]);
            if ($tiene->fetchColumn() > 0) {
                $error = 'No se puede eliminar: hay apuestas sobre este mercado.';
            } else {
                $db->prepare("DELETE FROM eventos WHERE id=? AND partido_id=?")->execute([$ev_id, $id]);
                $success = 'Mercado eliminado.';
            }
        }
        $eventos = $db->prepare("SELECT * FROM eventos WHERE partido_id=? ORDER BY id ASC");
        $eventos->execute([$id]); $eventos = $eventos->fetchAll();
    }
}

// Contar apuestas por evento
$apuestasPorEvento = [];
foreach ($eventos as $ev) {
    $c = $db->prepare("SELECT COUNT(*) FROM apuesta_detalle WHERE evento_id=?");
    $c->execute([$ev['id']]);
    $apuestasPorEvento[$ev['id']] = intval($c->fetchColumn());
}

$pageTitle = 'Editar Partido';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <div class="page-header d-flex align-center gap-2" style="justify-content:space-between;flex-wrap:wrap;">
        <div>
            <h1 class="page-title"><i class="fas fa-edit"></i> Editar: Bad Bunsy vs <?= sanitize($partido['rival']) ?></h1>
            <p class="page-subtitle">
                <?= date('d/m/Y', strtotime($partido['fecha'])) ?> ·
                <span class="match-status status-<?= $partido['estado'] ?>"><?= $partido['estado'] ?></span>
            </p>
        </div>
        <a href="<?= SITE_URL ?>/admin/partidos.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
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

    <div class="grid-2" style="gap:24px;align-items:start;">

        <!-- Izquierda: datos + agregar mercado -->
        <div>
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-futbol"></i> Datos del partido</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="guardar_partido">
                        <div class="form-group">
                            <label class="form-label">Rival</label>
                            <input type="text" name="rival" class="form-control"
                                   value="<?= sanitize($partido['rival']) ?>" required>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Fecha</label>
                                <input type="date" name="fecha" class="form-control"
                                       value="<?= $partido['fecha'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Hora</label>
                                <input type="time" name="hora" class="form-control"
                                       value="<?= substr($partido['hora']??'20:00',0,5) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-control">
                                <?php foreach (['abierto','cerrado','finalizado'] as $s): ?>
                                <option value="<?= $s ?>" <?= $partido['estado']===$s?'selected':'' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"><?= sanitize($partido['descripcion']??'') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Guardar cambios
                        </button>
                    </form>
                </div>
            </div>

            <!-- Agregar mercado -->
            <div class="card" style="margin-top:20px;">
                <div class="card-header"><span class="card-title"><i class="fas fa-plus-circle"></i> Agregar mercado</span></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="agregar_evento">
                        <div class="form-group">
                            <label class="form-label">Descripción *</label>
                            <input type="text" name="ev_desc" class="form-control"
                                   placeholder="Ej: Alex marca gol" required>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Cuota *</label>
                                <input type="number" name="ev_cuota" class="form-control"
                                       placeholder="Ej: 3.50" step="0.01" min="1.01" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jugador relacionado</label>
                                <select name="ev_jugador" class="form-control">
                                    <option value="ninguno">Sin jugador</option>
                                    <?php foreach ($jugadores as $j): ?>
                                    <option value="<?= sanitize($j) ?>"><?= sanitize($j) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-plus"></i> Agregar mercado
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Derecha: mercados actuales -->
        <div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-list"></i> Mercados actuales</span>
                    <span class="badge badge-blue"><?= count($eventos) ?></span>
                </div>

                <?php if (empty($eventos)): ?>
                <div class="empty-state" style="padding:30px;">
                    <i class="fas fa-list"></i><p>No hay mercados. Agrega uno.</p>
                </div>
                <?php else: ?>
                <?php foreach ($eventos as $ev):
                    $tieneAp = $apuestasPorEvento[$ev['id']] > 0;
                    $badgeRes = ['pendiente'=>'badge-gold','ganada'=>'badge-green','perdida'=>'badge-red'][$ev['resultado']] ?? 'badge-muted';
                    $jugBadge = $ev['jugador_relacionado'] !== 'ninguno'
                        ? '<span class="badge badge-blue" style="margin-left:6px;">'.$ev['jugador_relacionado'].'</span>' : '';
                ?>
                <div style="border-bottom:1px solid var(--border);padding:14px 20px;" id="row_<?= $ev['id'] ?>">
                    <!-- Vista normal -->
                    <div id="view_<?= $ev['id'] ?>" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:.9rem;">
                                <?= sanitize($ev['descripcion']) ?> <?= $jugBadge ?>
                            </div>
                            <div style="font-size:.77rem;color:var(--text-muted);margin-top:3px;">
                                Cuota: <strong class="text-gold"><?= number_format($ev['cuota'],2) ?>x</strong>
                                · <?= $apuestasPorEvento[$ev['id']] ?> apuesta(s)
                                · <span class="badge <?= $badgeRes ?>"><?= $ev['resultado'] ?></span>
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;">
                            <button onclick="mostrarEdicion(<?= $ev['id'] ?>)" class="btn btn-sm btn-outline">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if (!$tieneAp): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('¿Eliminar este mercado?')">
                                <input type="hidden" name="accion" value="eliminar_evento">
                                <input type="hidden" name="ev_id"  value="<?= $ev['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-outline" disabled style="opacity:.4;"
                                    title="Tiene apuestas activas"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edición inline -->
                    <div id="edit_<?= $ev['id'] ?>" style="display:none;margin-top:12px;background:var(--bg-hover);border-radius:8px;padding:14px;">
                        <form method="POST">
                            <input type="hidden" name="accion"  value="editar_evento">
                            <input type="hidden" name="ev_id"   value="<?= $ev['id'] ?>">
                            <div class="form-group" style="margin-bottom:10px;">
                                <label class="form-label" style="font-size:.78rem;">Descripción</label>
                                <input type="text" name="ev_desc_edit" class="form-control"
                                       value="<?= sanitize($ev['descripcion']) ?>" required>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label" style="font-size:.78rem;">Cuota</label>
                                    <input type="number" name="ev_cuota_edit" class="form-control"
                                           value="<?= $ev['cuota'] ?>" step="0.01" min="1.01" required>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label" style="font-size:.78rem;">Jugador</label>
                                    <select name="ev_jugador_edit" class="form-control">
                                        <option value="ninguno" <?= $ev['jugador_relacionado']==='ninguno'?'selected':'' ?>>Sin jugador</option>
                                        <?php foreach ($jugadores as $j): ?>
                                        <option value="<?= sanitize($j) ?>" <?= $ev['jugador_relacionado']===$j?'selected':'' ?>>
                                            <?= sanitize($j) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Guardar</button>
                                <button type="button" onclick="ocultarEdicion(<?= $ev['id'] ?>)" class="btn btn-outline btn-sm">Cancelar</button>
                            </div>
                        </form>
                        <form id="formEliminar_<?= $ev['id'] ?>" method="POST" style="display:none;">
                            <input type="hidden" name="accion" value="eliminar_evento">
                            <input type="hidden" name="ev_id"  value="<?= $ev['id'] ?>">
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function mostrarEdicion(id) {
    document.getElementById('view_' + id).style.display = 'none';
    document.getElementById('edit_' + id).style.display = 'block';
}
function ocultarEdicion(id) {
    document.getElementById('edit_' + id).style.display = 'none';
    document.getElementById('view_' + id).style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>