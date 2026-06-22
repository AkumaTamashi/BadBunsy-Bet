<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db   = getDB();
$user = getCurrentUser();

$error   = '';
$success = '';
$destinatario = null;

// ============================================================
// BUSCAR USUARIO (AJAX)
// ============================================================
if (isset($_GET['buscar_ajax'])) {
    header('Content-Type: application/json');
    $q = sanitize($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit(); }

    $stmt = $db->prepare("
        SELECT id, nombre, jugador_asociado
        FROM usuarios
        WHERE rol = 'usuario'
          AND activo = 1
          AND id != ?
          AND (nombre LIKE ? OR jugador_asociado LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$user['id'], "%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll());
    exit();
}

// ============================================================
// PROCESAR TRANSFERENCIA
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dest_id = intval($_POST['destinatario_id'] ?? 0);
    $monto   = floatval($_POST['monto']          ?? 0);
    $nota    = sanitize($_POST['nota']           ?? '');

    // Validaciones
    if ($dest_id <= 0) {
        $error = 'Selecciona un destinatario válido.';
    } elseif ($dest_id === $user['id']) {
        $error = 'No puedes enviarte dinero a ti mismo.';
    } elseif ($monto < 100) {
        $error = 'El monto mínimo para transferir es 100 BB.';
    } else {
        // Verificar saldo fresco desde BD
        $saldoActual = floatval($db->prepare("SELECT saldo FROM usuarios WHERE id=?")->execute([$user['id']]) ? $db->prepare("SELECT saldo FROM usuarios WHERE id=?")->execute([$user['id']]) : 0);
        $stmtSaldo = $db->prepare("SELECT saldo FROM usuarios WHERE id = ?");
        $stmtSaldo->execute([$user['id']]);
        $saldoActual = floatval($stmtSaldo->fetchColumn());

        if ($monto > $saldoActual) {
            $error = 'Saldo insuficiente. Tienes ' . number_format($saldoActual, 0, ',', '.') . ' BB.';
        } else {
            // Verificar destinatario existe
            $stmtDest = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'usuario' AND activo = 1");
            $stmtDest->execute([$dest_id]);
            $destinatario = $stmtDest->fetch();

            if (!$destinatario) {
                $error = 'El destinatario no existe o no es válido.';
            } else {
                try {
                    $db->beginTransaction();

                    $notaFinal = $nota ? " — \"$nota\"" : '';
                    $monto     = round($monto);

                    // Descontar al emisor
                    $db->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?")
                       ->execute([$monto, $user['id']]);

                    // Sumar al destinatario
                    $db->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")
                       ->execute([$monto, $dest_id]);

                    // Movimiento emisor
                    $db->prepare("INSERT INTO movimientos (usuario_id, tipo, monto, descripcion) VALUES (?, 'retiro', ?, ?)")
                       ->execute([$user['id'], $monto,
                           'Transferencia enviada a ' . $destinatario['nombre'] . $notaFinal]);

                    // Movimiento destinatario
                    $db->prepare("INSERT INTO movimientos (usuario_id, tipo, monto, descripcion) VALUES (?, 'deposito', ?, ?)")
                       ->execute([$dest_id, $monto,
                           'Transferencia recibida de ' . $user['nombre'] . $notaFinal]);

                    $db->commit();

                    $success = '¡Transferencia exitosa! Enviaste ' . number_format($monto, 0, ',', '.') . ' BB a ' . $destinatario['nombre'] . '.';
                    $destinatario = null; // limpiar form

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Error al procesar la transferencia. Intenta de nuevo.';
                }
            }
        }
    }
}

// ============================================================
// HISTORIAL DE TRANSFERENCIAS DEL USUARIO
// ============================================================
$historial = $db->prepare("
    SELECT * FROM movimientos
    WHERE usuario_id = ?
      AND tipo IN ('retiro', 'deposito')
      AND descripcion LIKE '%ransferencia%'
    ORDER BY fecha DESC
    LIMIT 20
");
$historial->execute([$user['id']]);
$historial = $historial->fetchAll();

// Saldo actualizado
$user = getCurrentUser();

$pageTitle = 'Transferir BB';
include __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">

<div class="container" style="max-width:900px;">

    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-paper-plane"></i> Transferir BB</h1>
        <p class="page-subtitle">Envía saldo virtual a otros miembros del club</p>
    </div>

    <?php if ($error): ?>
    <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:20px;animation:none;">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flash-message flash-success" style="position:relative;top:auto;right:auto;margin-bottom:20px;animation:none;">
        <i class="fas fa-check-circle"></i> <?= sanitize($success) ?>
    </div>
    <?php endif; ?>

    <div class="grid-2" style="align-items:start;gap:24px;">

        <!-- ===== FORMULARIO ===== -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-exchange-alt"></i> Nueva transferencia</span>
                <div class="nav-balance" style="font-size:0.85rem;padding:6px 12px;">
                    <i class="fas fa-coins"></i>
                    <span id="saldoDisplay"><?= formatMoney($user['saldo']) ?> BB</span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="transferForm">

                    <!-- Búsqueda de destinatario -->
                    <div class="form-group" style="position:relative;">
                        <label class="form-label">Buscar destinatario</label>
                        <div style="position:relative;">
                            <input
                                type="text"
                                id="buscarInput"
                                class="form-control"
                                placeholder="Escribe nombre o jugador..."
                                autocomplete="off"
                                oninput="buscarUsuarios(this.value)"
                            >
                            <i class="fas fa-search" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                        </div>

                        <!-- Dropdown resultados -->
                        <div id="searchDropdown" style="
                            display:none;
                            position:absolute;
                            top:100%;
                            left:0; right:0;
                            background:var(--bg-secondary);
                            border:1px solid var(--border);
                            border-radius:0 0 8px 8px;
                            z-index:200;
                            box-shadow:0 8px 24px rgba(0,0,0,0.4);
                            overflow:hidden;
                        "></div>
                    </div>

                    <!-- Destinatario seleccionado -->
                    <div id="destinatarioSeleccionado" style="display:none;margin-bottom:18px;">
                        <div style="
                            background:var(--green-glow);
                            border:1px solid var(--green);
                            border-radius:10px;
                            padding:14px 16px;
                            display:flex;
                            align-items:center;
                            justify-content:space-between;
                            gap:12px;
                        ">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div class="user-avatar" id="destAvatar" style="width:40px;height:40px;font-size:0.9rem;flex-shrink:0;"></div>
                                <div>
                                    <div style="font-weight:700;" id="destNombre">—</div>
                                    <div style="font-size:0.78rem;color:var(--green);" id="destJugador">—</div>
                                </div>
                            </div>
                            <button type="button" onclick="limpiarDestinatario()"
                                style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.1rem;"
                                title="Cambiar destinatario">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                        <input type="hidden" name="destinatario_id" id="destinatarioId" value="">
                    </div>

                    <!-- Monto -->
                    <div class="form-group">
                        <label class="form-label">Monto a enviar (BB)</label>
                        <input
                            type="number"
                            name="monto"
                            id="montoInput"
                            class="form-control"
                            placeholder="Ej: 5.000"
                            min="100"
                            step="100"
                            oninput="actualizarResumen()"
                        >
                        <!-- Botones rápidos -->
                        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
                            <?php foreach ([1000, 5000, 10000, 25000, 50000] as $q): ?>
                            <button type="button" class="bet-quick-btn" onclick="setMonto(<?= $q ?>)">
                                <?= formatMoney($q) ?>
                            </button>
                            <?php endforeach; ?>
                            <button type="button" class="bet-quick-btn" onclick="setMonto(<?= floor($user['saldo']) ?>)" style="color:var(--green);">
                                Todo
                            </button>
                        </div>
                    </div>

                    <!-- Nota opcional -->
                    <div class="form-group">
                        <label class="form-label">Nota <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
                        <input
                            type="text"
                            name="nota"
                            class="form-control"
                            placeholder="Ej: Te debo de la apuesta del martes"
                            maxlength="80"
                        >
                    </div>

                    <!-- Resumen -->
                    <div id="resumenTransferencia" style="display:none;margin-bottom:18px;">
                        <div style="background:var(--bg-hover);border-radius:10px;border:1px solid var(--border);padding:14px 18px;">
                            <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">
                                Resumen
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.875rem;margin-bottom:6px;">
                                <span style="color:var(--text-muted);">Envías</span>
                                <span class="fw-bold" id="resumenMonto">— BB</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.875rem;margin-bottom:6px;">
                                <span style="color:var(--text-muted);">Tu saldo después</span>
                                <span class="fw-bold" id="resumenSaldoPost">— BB</span>
                            </div>
                            <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;font-size:1rem;">
                                <span style="color:var(--text-muted);">Recibirá</span>
                                <span class="fw-bold text-green" id="resumenGanancia">— BB</span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        onclick="confirmarTransferencia()"
                        class="btn btn-primary btn-lg btn-block"
                        id="btnTransferir"
                        disabled
                    >
                        <i class="fas fa-paper-plane"></i> Enviar transferencia
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== HISTORIAL ===== -->
        <div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-history"></i> Historial de transferencias</span>
                </div>

                <?php if (empty($historial)): ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <i class="fas fa-paper-plane"></i>
                    <p>Aún no has realizado ni recibido transferencias</p>
                </div>
                <?php else: ?>
                <?php foreach ($historial as $mov):
                    $esEnvio   = $mov['tipo'] === 'retiro';
                    $icono     = $esEnvio ? 'fa-arrow-up' : 'fa-arrow-down';
                    $colorIcon = $esEnvio ? 'var(--red)' : 'var(--green)';
                    $signo     = $esEnvio ? '-' : '+';
                    $colorMonto= $esEnvio ? 'text-red' : 'text-green';
                ?>
                <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border);">
                    <div style="
                        width:38px;height:38px;border-radius:50%;flex-shrink:0;
                        background:<?= $esEnvio ? 'rgba(239,68,68,0.12)' : 'rgba(34,197,94,0.12)' ?>;
                        display:flex;align-items:center;justify-content:center;
                        color:<?= $colorIcon ?>;
                    ">
                        <i class="fas <?= $icono ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= sanitize($mov['descripcion']) ?>
                        </div>
                        <div style="font-size:0.73rem;color:var(--text-muted);margin-top:2px;">
                            <?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?>
                        </div>
                    </div>
                    <div style="font-size:0.95rem;font-weight:800;flex-shrink:0;" class="<?= $colorMonto ?>">
                        <?= $signo ?><?= formatMoney($mov['monto']) ?> BB
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <div style="padding:12px 20px;">
                    <a href="<?= SITE_URL ?>/usuarios/movimientos.php" class="btn btn-outline btn-sm btn-block">
                        Ver todos los movimientos
                    </a>
                </div>
            </div>

            <!-- Info -->
            <div class="card" style="margin-top:16px;">
                <div class="card-body" style="padding:16px 18px;">
                    <p style="font-size:0.8rem;font-weight:700;color:var(--text-muted);margin-bottom:8px;">
                        <i class="fas fa-info-circle" style="color:var(--blue);"></i> ¿Cómo funciona?
                    </p>
                    <ul style="font-size:0.78rem;color:var(--text-muted);padding-left:16px;line-height:2;">
                        <li>Busca por <strong>nombre de usuario</strong> o <strong>jugador asociado</strong>.</li>
                        <li>Monto mínimo: <strong>100 BB</strong>.</li>
                        <li>Las transferencias son <strong>inmediatas</strong> e irreversibles.</li>
                        <li>Puedes añadir una nota que verá el destinatario.</li>
                        <li>Quedan registradas en el historial de ambos.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal-overlay" id="modalTransferencia">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">
                <i class="fas fa-paper-plane" style="color:var(--green)"></i>
                Confirmar transferencia
            </span>
            <button class="modal-close" onclick="closeModal('modalTransferencia')">×</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:20px;">
                <div class="user-avatar" id="modalDestAvatar"
                     style="width:56px;height:56px;font-size:1.2rem;border-radius:50%;margin:0 auto 10px;"></div>
                <div style="font-size:1.1rem;font-weight:700;" id="modalDestNombre"></div>
                <div style="font-size:0.8rem;color:var(--text-muted);" id="modalDestJugador"></div>
            </div>
            <div style="background:var(--bg-hover);border-radius:10px;padding:16px;margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:0.875rem;">
                    <span style="color:var(--text-muted);">Monto a enviar</span>
                    <span class="fw-bold text-green" style="font-size:1.2rem;" id="modalMonto"></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.875rem;">
                    <span style="color:var(--text-muted);">Tu saldo quedará en</span>
                    <span class="fw-bold" id="modalSaldoPost"></span>
                </div>
            </div>
            <div id="modalNota" style="display:none;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:8px;padding:10px 14px;font-size:0.82rem;color:var(--text-secondary);margin-bottom:12px;">
                <i class="fas fa-comment" style="color:var(--blue);"></i> <span id="modalNotaTexto"></span>
            </div>
            <p style="font-size:0.78rem;color:var(--text-muted);text-align:center;">
                <i class="fas fa-exclamation-triangle"></i>
                Las transferencias son inmediatas e irreversibles.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('modalTransferencia')" class="btn btn-outline">Cancelar</button>
            <button onclick="enviarTransferencia()" class="btn btn-primary" id="btnConfirmarModal">
                <i class="fas fa-check"></i> Confirmar envío
            </button>
        </div>
    </div>
</div>

<script>
const SALDO_ACTUAL = <?= floatval($user['saldo']) ?>;
let destSeleccionado = null;
let searchTimer      = null;

// ============================================================
// BÚSQUEDA
// ============================================================
function buscarUsuarios(q) {
    clearTimeout(searchTimer);
    const dropdown = document.getElementById('searchDropdown');

    if (q.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    searchTimer = setTimeout(() => {
        fetch(`?buscar_ajax=1&q=${encodeURIComponent(q)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    dropdown.innerHTML = `
                        <div style="padding:14px 16px;font-size:0.85rem;color:var(--text-muted);text-align:center;">
                            <i class="fas fa-search"></i> Sin resultados para "${q}"
                        </div>`;
                } else {
                    dropdown.innerHTML = data.map(u => `
                        <div onclick="seleccionarDestinatario(${u.id}, '${escapar(u.nombre)}', '${escapar(u.jugador_asociado)}')"
                             style="display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;transition:background .15s;border-bottom:1px solid var(--border);"
                             onmouseover="this.style.background='var(--bg-hover)'"
                             onmouseout="this.style.background=''"
                        >
                            <div class="user-avatar" style="width:36px;height:36px;font-size:0.8rem;flex-shrink:0;">
                                ${u.nombre.substring(0,2).toUpperCase()}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:0.875rem;">${u.nombre}</div>
                                <div style="font-size:0.75rem;color:var(--green);">${u.jugador_asociado}</div>
                            </div>
                        </div>
                    `).join('');
                }
                dropdown.style.display = 'block';
            })
            .catch(() => { dropdown.style.display = 'none'; });
    }, 280);
}

function escapar(s) {
    return s.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function seleccionarDestinatario(id, nombre, jugador) {
    destSeleccionado = { id, nombre, jugador };

    document.getElementById('searchDropdown').style.display = 'none';
    document.getElementById('buscarInput').value = '';

    document.getElementById('destinatarioId').value    = id;
    document.getElementById('destNombre').textContent  = nombre;
    document.getElementById('destJugador').textContent = jugador;
    document.getElementById('destAvatar').textContent  = nombre.substring(0,2).toUpperCase();
    document.getElementById('destinatarioSeleccionado').style.display = 'block';

    actualizarResumen();
    validarBoton();
}

function limpiarDestinatario() {
    destSeleccionado = null;
    document.getElementById('destinatarioSeleccionado').style.display = 'none';
    document.getElementById('destinatarioId').value = '';
    document.getElementById('buscarInput').value    = '';
    document.getElementById('resumenTransferencia').style.display = 'none';
    validarBoton();
}

// ============================================================
// RESUMEN Y VALIDACIÓN
// ============================================================
function setMonto(val) {
    document.getElementById('montoInput').value = val;
    actualizarResumen();
}

function actualizarResumen() {
    const monto = parseFloat(document.getElementById('montoInput').value || 0);
    const resumen = document.getElementById('resumenTransferencia');

    if (monto >= 100 && destSeleccionado) {
        const post = SALDO_ACTUAL - monto;
        document.getElementById('resumenMonto').textContent    = formatNum(monto) + ' BB';
        document.getElementById('resumenGanancia').textContent = formatNum(monto) + ' BB';
        document.getElementById('resumenSaldoPost').textContent = formatNum(post) + ' BB';
        document.getElementById('resumenSaldoPost').style.color = post < 0 ? 'var(--red)' : 'var(--text-primary)';
        resumen.style.display = 'block';
    } else {
        resumen.style.display = 'none';
    }
    validarBoton();
}

function validarBoton() {
    const monto = parseFloat(document.getElementById('montoInput').value || 0);
    const ok    = destSeleccionado && monto >= 100 && monto <= SALDO_ACTUAL;
    document.getElementById('btnTransferir').disabled = !ok;
}

// ============================================================
// MODAL CONFIRMACIÓN
// ============================================================
function confirmarTransferencia() {
    if (!destSeleccionado) return;
    const monto = parseFloat(document.getElementById('montoInput').value || 0);
    const nota  = document.querySelector('input[name="nota"]').value.trim();
    const post  = SALDO_ACTUAL - monto;

    document.getElementById('modalDestAvatar').textContent  = destSeleccionado.nombre.substring(0,2).toUpperCase();
    document.getElementById('modalDestNombre').textContent  = destSeleccionado.nombre;
    document.getElementById('modalDestJugador').textContent = destSeleccionado.jugador;
    document.getElementById('modalMonto').textContent       = formatNum(monto) + ' BB';
    document.getElementById('modalSaldoPost').textContent   = formatNum(post) + ' BB';
    document.getElementById('modalSaldoPost').style.color   = post < 0 ? 'var(--red)' : '';

    const notaEl = document.getElementById('modalNota');
    if (nota) {
        document.getElementById('modalNotaTexto').textContent = nota;
        notaEl.style.display = 'block';
    } else {
        notaEl.style.display = 'none';
    }

    document.getElementById('modalTransferencia').classList.add('show');
}

function enviarTransferencia() {
    const btn = document.getElementById('btnConfirmarModal');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    document.getElementById('transferForm').submit();
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('show');
}

// Cerrar dropdown al click fuera
document.addEventListener('click', e => {
    if (!e.target.closest('#buscarInput') && !e.target.closest('#searchDropdown')) {
        document.getElementById('searchDropdown').style.display = 'none';
    }
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show');
});

// ============================================================
// UTILS
// ============================================================
function formatNum(n) {
    return parseFloat(n).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>