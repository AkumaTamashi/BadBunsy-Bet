<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$error = $success = '';

// ============================================================
// PROCESAR RESULTADOS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partido_id = intval($_POST['partido_id'] ?? 0);
    $resultados = $_POST['resultado'] ?? []; // [evento_id => 'ganada'|'perdida']

    if ($partido_id <= 0) {
        $error = 'Partido inválido.';
    } elseif (empty($resultados)) {
        $error = 'Marca al menos un resultado.';
    } else {
        try {
            $db->beginTransaction();

            // 1. Actualizar resultado de cada evento marcado
            $stmtEv = $db->prepare("UPDATE eventos SET resultado=? WHERE id=? AND partido_id=?");
            foreach ($resultados as $ev_id => $res) {
                if (in_array($res, ['ganada','perdida'])) {
                    $stmtEv->execute([$res, intval($ev_id), $partido_id]);
                }
            }

            // 2. Verificar si todos los eventos tienen resultado
            $pendientes = $db->prepare("SELECT COUNT(*) FROM eventos WHERE partido_id=? AND resultado='pendiente'");
            $pendientes->execute([$partido_id]);
            $hayPendientes = $pendientes->fetchColumn() > 0;

            // 3. Procesar apuestas pendientes del partido
            $apuestas = $db->prepare("
                SELECT a.* FROM apuestas a
                WHERE a.partido_id = ? AND a.estado = 'pendiente'
            ");
            $apuestas->execute([$partido_id]);
            $apuestas = $apuestas->fetchAll();

            $ganadas = $perdidas = 0;

            foreach ($apuestas as $ap) {
                // Obtener eventos de esta apuesta
                $evs = $db->prepare("
                    SELECT e.resultado FROM apuesta_detalle ad
                    JOIN eventos e ON e.id = ad.evento_id
                    WHERE ad.apuesta_id = ?
                ");
                $evs->execute([$ap['id']]);
                $evs = $evs->fetchAll();

                // Verificar si todos los eventos de la apuesta tienen resultado
                $todosResueltos = !array_filter($evs, fn($e) => $e['resultado'] === 'pendiente');

                if (!$todosResueltos) continue; // aún hay eventos sin resultado

                // ¿Ganó? Todos los eventos deben ser 'ganada'
                $gano = !array_filter($evs, fn($e) => $e['resultado'] !== 'ganada');

                if ($gano) {
                    // Sumar ganancia al saldo
                    $db->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")
                       ->execute([$ap['posible_ganancia'], $ap['usuario_id']]);
                    $db->prepare("UPDATE apuestas SET estado = 'ganada' WHERE id = ?")
                       ->execute([$ap['id']]);
                    $db->prepare("INSERT INTO movimientos (usuario_id, tipo, monto, descripcion) VALUES (?,?,?,?)")
                       ->execute([
                           $ap['usuario_id'], 'ganancia', $ap['posible_ganancia'],
                           'Ganancia apuesta #' . $ap['id']
                       ]);
                    $ganadas++;
                } else {
                    $db->prepare("UPDATE apuestas SET estado = 'perdida' WHERE id = ?")
                       ->execute([$ap['id']]);
                    $perdidas++;
                }
            }

            // 4. Si no quedan pendientes, finalizar el partido
            if (!$hayPendientes) {
                $db->prepare("UPDATE partidos SET estado='finalizado' WHERE id=?")
                   ->execute([$partido_id]);
            }

            $db->commit();

            $success = "✓ Resultados guardados. Apuestas procesadas: $ganadas ganadas, $perdidas perdidas.";
            if (!$hayPendientes) {
                $success .= ' Partido marcado como finalizado.';
            }

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al procesar: ' . $e->getMessage();
        }
    }
}

// ============================================================
// CARGAR PARTIDOS CON APUESTAS PENDIENTES
// ============================================================
$partido_id_get = intval($_GET['id'] ?? 0);

$partidos_pendientes = $db->query("
    SELECT p.*, COUNT(DISTINCT a.id) as num_apuestas
    FROM partidos p
    LEFT JOIN apuestas a ON a.partido_id = p.id AND a.estado = 'pendiente'
    WHERE p.estado IN ('cerrado','abierto')
    GROUP BY p.id
    ORDER BY p.fecha DESC
")->fetchAll();

// Partido seleccionado
$partido_sel = null;
$eventos_sel = [];

if ($partido_id_get > 0) {
    $partido_sel = $db->prepare("SELECT * FROM partidos WHERE id=?");
    $partido_sel->execute([$partido_id_get]);
    $partido_sel = $partido_sel->fetch();

    if ($partido_sel) {
        $eventos_sel = $db->prepare("SELECT * FROM eventos WHERE partido_id=? ORDER BY id ASC");
        $eventos_sel->execute([$partido_id_get]);
        $eventos_sel = $eventos_sel->fetchAll();
    }
}

// Si POST fue exitoso, recargar el partido
if ($success && $partido_id_get > 0 && $partido_sel) {
    $eventos_sel = $db->prepare("SELECT * FROM eventos WHERE partido_id=? ORDER BY id ASC");
    $eventos_sel->execute([$partido_id_get]);
    $eventos_sel = $eventos_sel->fetchAll();
    $partido_sel = $db->prepare("SELECT * FROM partidos WHERE id=?");
    $partido_sel->execute([$partido_id_get]);
    $partido_sel = $partido_sel->fetch();
}

$pageTitle = 'Cargar Resultados';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-check-double"></i> Cargar Resultados</h1>
        <p class="page-subtitle">Marca el resultado de cada mercado y el sistema liquidará las apuestas automáticamente</p>
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

    <div class="grid-2" style="align-items:start;gap:24px;">

        <!-- Lista de partidos -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-futbol"></i> Selecciona un partido</span>
            </div>
            <?php if (empty($partidos_pendientes)): ?>
            <div class="empty-state" style="padding:30px;">
                <i class="fas fa-check-circle" style="color:var(--green)"></i>
                <p>No hay partidos pendientes de resultados</p>
            </div>
            <?php else: ?>
            <?php foreach ($partidos_pendientes as $p): ?>
            <a href="?id=<?= $p['id'] ?>"
               style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);text-decoration:none;transition:background .15s;<?= $partido_id_get===$p['id']?'background:var(--bg-hover);border-left:3px solid var(--green);':'' ?>"
               onmouseover="this.style.background='var(--bg-hover)'"
               onmouseout="this.style.background='<?= $partido_id_get===$p['id']?'var(--bg-hover)':'' ?>'">
                <div>
                    <div style="font-weight:700;color:var(--text-primary);">
                        Bad Bunsy vs <?= sanitize($p['rival']) ?>
                    </div>
                    <div style="font-size:.77rem;color:var(--text-muted);margin-top:2px;">
                        <?= date('d/m/Y', strtotime($p['fecha'])) ?>
                        · <span class="match-status status-<?= $p['estado'] ?>" style="font-size:.65rem;"><?= $p['estado'] ?></span>
                    </div>
                </div>
                <div style="text-align:right;">
                    <?php if ($p['num_apuestas'] > 0): ?>
                    <span class="badge badge-gold"><?= $p['num_apuestas'] ?> pendientes</span>
                    <?php else: ?>
                    <span class="badge badge-muted">Sin apuestas</span>
                    <?php endif; ?>
                    <i class="fas fa-chevron-right" style="color:var(--text-muted);margin-left:8px;font-size:.75rem;"></i>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Panel de resultados -->
        <div>
            <?php if (!$partido_sel): ?>
            <div class="card">
                <div class="empty-state" style="padding:60px 20px;">
                    <i class="fas fa-arrow-left" style="font-size:2rem;"></i>
                    <p style="margin-top:12px;">Selecciona un partido de la lista para cargar sus resultados</p>
                </div>
            </div>
            <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">
                            <i class="fas fa-list-check"></i>
                            vs <?= sanitize($partido_sel['rival']) ?>
                        </span>
                        <div style="font-size:.77rem;color:var(--text-muted);margin-top:3px;">
                            <?= date('d/m/Y', strtotime($partido_sel['fecha'])) ?>
                            · <span class="match-status status-<?= $partido_sel['estado'] ?>" style="font-size:.65rem;"><?= $partido_sel['estado'] ?></span>
                        </div>
                    </div>
                    <button onclick="marcarTodos('ganada')" class="btn btn-sm btn-primary">
                        <i class="fas fa-check"></i> Todos ganados
                    </button>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="partido_id" value="<?= $partido_sel['id'] ?>">

                    <?php if (empty($eventos_sel)): ?>
                    <div class="empty-state" style="padding:30px;"><p>No hay mercados en este partido.</p></div>
                    <?php else: ?>

                    <div style="padding:8px 16px;">
                        <p style="font-size:.78rem;color:var(--text-muted);padding:8px 0;">
                            <i class="fas fa-info-circle" style="color:var(--blue)"></i>
                            Solo marca los eventos que ya tienen resultado. Los que dejes sin marcar quedan como pendientes.
                        </p>
                    </div>

                    <?php foreach ($eventos_sel as $ev): ?>
                    <?php
                        $badgeRes = ['pendiente'=>'badge-gold','ganada'=>'badge-green','perdida'=>'badge-red'][$ev['resultado']] ?? 'badge-muted';
                        // Contar apuestas afectadas
                        $nAp = $db->prepare("SELECT COUNT(*) FROM apuesta_detalle WHERE evento_id=?");
                        $nAp->execute([$ev['id']]); $nAp = $nAp->fetchColumn();
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px;">
                        <div style="flex:1;min-width:160px;">
                            <div style="font-weight:600;font-size:.875rem;"><?= sanitize($ev['descripcion']) ?></div>
                            <div style="font-size:.73rem;color:var(--text-muted);margin-top:3px;">
                                Cuota: <span class="text-gold"><?= number_format($ev['cuota'],2) ?>x</span>
                                · <?= $nAp ?> apuesta(s)
                                · Estado actual: <span class="badge <?= $badgeRes ?>" style="font-size:.65rem;"><?= $ev['resultado'] ?></span>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="resultado[<?= $ev['id'] ?>]"
                                   id="resultado_<?= $ev['id'] ?>"
                                   value="<?= $ev['resultado'] !== 'pendiente' ? $ev['resultado'] : '' ?>">
                            <button type="button"
                                    class="btn btn-sm <?= $ev['resultado']==='ganada'?'btn-primary':'btn-outline' ?>"
                                    data-evento-id="<?= $ev['id'] ?>" data-resultado="ganada"
                                    onclick="setResultado(<?= $ev['id'] ?>, 'ganada')">
                                <i class="fas fa-check"></i> Ganada
                            </button>
                            <button type="button"
                                    class="btn btn-sm <?= $ev['resultado']==='perdida'?'btn-danger':'btn-outline' ?>"
                                    data-evento-id="<?= $ev['id'] ?>" data-resultado="perdida"
                                    onclick="setResultado(<?= $ev['id'] ?>, 'perdida')">
                                <i class="fas fa-times"></i> Perdida
                            </button>
                            <?php if ($ev['resultado'] !== 'pendiente'): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline"
                                    onclick="setResultado(<?= $ev['id'] ?>, 'pendiente')"
                                    title="Quitar resultado">
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="padding:20px;border-top:1px solid var(--border);display:flex;gap:12px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary btn-lg"
                                onclick="return confirm('¿Guardar resultados y liquidar apuestas?')">
                            <i class="fas fa-save"></i> Guardar resultados y liquidar
                        </button>
                        <a href="<?= SITE_URL ?>/admin/partidos.php" class="btn btn-outline btn-lg">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Resumen de apuestas del partido -->
            <?php
            $resumen = $db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado='ganada'    THEN 1 ELSE 0 END) as ganadas,
                    SUM(CASE WHEN estado='perdida'   THEN 1 ELSE 0 END) as perdidas,
                    SUM(monto) as total_apostado,
                    SUM(CASE WHEN estado='ganada' THEN posible_ganancia ELSE 0 END) as total_pagado
                FROM apuestas WHERE partido_id=?
            ");
            $resumen->execute([$partido_sel['id']]);
            $resumen = $resumen->fetch();
            ?>
            <?php if ($resumen['total'] > 0): ?>
            <div class="card" style="margin-top:16px;">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-chart-bar"></i> Resumen de apuestas</span>
                </div>
                <div class="card-body" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center;">
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;">
                        <div style="font-size:1.4rem;font-weight:800;color:var(--gold);"><?= $resumen['pendientes'] ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Pendientes</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;">
                        <div style="font-size:1.4rem;font-weight:800;color:var(--green);"><?= $resumen['ganadas'] ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Ganadas</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;">
                        <div style="font-size:1.4rem;font-weight:800;color:var(--red);"><?= $resumen['perdidas'] ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Perdidas</div>
                    </div>
                    <div style="padding:12px;background:var(--bg-hover);border-radius:8px;grid-column:1/3;">
                        <div style="font-size:1rem;font-weight:800;"><?= formatMoney($resumen['total_apostado']) ?> BB</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Total apostado</div>
                    </div>
                    <div style="padding:12px;background:rgba(34,197,94,.08);border:1px solid var(--green);border-radius:8px;">
                        <div style="font-size:1rem;font-weight:800;color:var(--green);"><?= formatMoney($resumen['total_pagado']) ?> BB</div>
                        <div style="font-size:.75rem;color:var(--text-muted);">Pagado</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script>
function marcarTodos(resultado) {
    document.querySelectorAll('input[name^="resultado["]').forEach(inp => {
        const id = inp.id.replace('resultado_', '');
        setResultado(parseInt(id), resultado);
    });
}

function setResultado(eventoId, resultado) {
    const hidden = document.getElementById('resultado_' + eventoId);

    document.querySelectorAll(`[data-evento-id="${eventoId}"]`).forEach(btn => {
        btn.classList.remove('btn-primary', 'btn-danger');
        btn.classList.add('btn-outline');
    });

    if (resultado === 'pendiente') {
        if (hidden) hidden.value = '';
        return;
    }

    const selected = document.querySelector(`[data-evento-id="${eventoId}"][data-resultado="${resultado}"]`);
    if (selected) {
        selected.classList.remove('btn-outline');
        selected.classList.add(resultado === 'ganada' ? 'btn-primary' : 'btn-danger');
    }
    if (hidden) hidden.value = resultado;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>