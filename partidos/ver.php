<?php

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db = getDB();

$user = getCurrentUser();

$id = intval($_GET['id'] ?? 0);

// ======================================================
// OBTENER PARTIDO
// ======================================================

$stmt = $db->prepare("
    SELECT *
    FROM partidos
    WHERE id = ?
");

$stmt->execute([$id]);

$partido = $stmt->fetch();

if (!$partido) {

    flashMessage('error', 'Partido no encontrado.');

    header('Location: ' . SITE_URL . '/partidos/lista.php');

    exit();
}

// ======================================================
// OBTENER EVENTOS
// ======================================================

$stmt = $db->prepare("
    SELECT *
    FROM eventos
    WHERE partido_id = ?
    ORDER BY id ASC
");

$stmt->execute([$id]);

$eventos = $stmt->fetchAll();

$pageTitle = 'vs ' . $partido['rival'];

include __DIR__ . '/../includes/header.php';

?>

<meta name="site-url" content="<?= SITE_URL ?>">

<meta
    name="jugador-asociado"
    content="<?= htmlspecialchars($user['jugador_asociado'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
>

<div class="container">

    <!-- HEADER PARTIDO -->

    <div class="match-card mb-3" style="margin-bottom:24px;">

        <div class="match-header" style="padding:24px;">

            <div>

                <div class="match-teams" style="font-size:1.5rem;">

                    <span style="color:var(--green);">
                        Bad Bunsy
                    </span>

                    <span class="match-vs">
                        VS
                    </span>

                    <span>
                        <?= sanitize($partido['rival']) ?>
                    </span>

                </div>

                <div
                    class="match-date"
                    style="margin-top:8px;font-size:0.9rem;"
                >

                    <i class="fas fa-calendar"></i>

                    <?= date('d/m/Y', strtotime($partido['fecha'])) ?>

                    &nbsp;·&nbsp;

                    <i class="fas fa-clock"></i>

                    <?= substr($partido['hora'] ?? '00:00', 0, 5) ?>

                </div>

                <?php if (!empty($partido['descripcion'])): ?>

                    <div
                        style="
                            margin-top:6px;
                            font-size:0.83rem;
                            color:var(--text-muted);
                        "
                    >

                        <?= sanitize($partido['descripcion']) ?>

                    </div>

                <?php endif; ?>

            </div>

            <span
                class="match-status status-<?= sanitize($partido['estado']) ?>"
            >

                <?= strtoupper($partido['estado']) ?>

            </span>

        </div>

    </div>

    <!-- ALERTA -->

    <?php if ($partido['estado'] !== 'abierto'): ?>

        <div
            class="flash-message flash-info"
            style="
                position:relative;
                top:auto;
                right:auto;
                margin-bottom:20px;
                animation:none;
            "
        >

            <i class="fas fa-info-circle"></i>

            <?=
                $partido['estado'] === 'cerrado'
                ? 'Las apuestas están cerradas.'
                : 'El partido ha finalizado.'
            ?>

        </div>

    <?php endif; ?>

    <div class="grid-2">

        <!-- EVENTOS -->

        <div>

            <h2
                class="page-title"
                style="font-size:1.1rem;margin-bottom:16px;"
            >

                <i class="fas fa-list"></i>

                Mercados Disponibles

            </h2>

            <?php if (empty($eventos)): ?>

                <div class="card">

                    <div class="empty-state">

                        <p>
                            No hay eventos disponibles.
                        </p>

                    </div>

                </div>

            <?php else: ?>

                <div class="card">

                    <div class="card-body" style="padding:16px;">

                        <?php foreach ($eventos as $ev): ?>

                            <?php

                            $esPropio = jugadorRelacionadoConEvento(
                                $user['jugador_asociado'],
                                $ev['jugador_relacionado']
                            );

                            $bloqueado =
                                $esPropio ||
                                $partido['estado'] !== 'abierto' ||
                                $ev['resultado'] !== 'pendiente';

                            ?>

                            <div style="margin-bottom:8px;">

                                <button

                                    class="odd-btn <?= $bloqueado ? 'blocked' : '' ?>"

                                    data-evento="<?= $ev['id'] ?>"

                                    data-partido="<?= $partido['id'] ?>"

                                    data-descripcion="<?= htmlspecialchars($ev['descripcion'], ENT_QUOTES, 'UTF-8') ?>"

                                    data-cuota="<?= $ev['cuota'] ?>"

                                    data-jugador="<?= htmlspecialchars($ev['jugador_relacionado'] ?? 'ninguno', ENT_QUOTES, 'UTF-8') ?>"

                                    <?= $bloqueado ? 'disabled' : '' ?>

                                    style="width:100%;"

                                >

                                    <div>

                                        <div class="odd-name">

                                            <?= sanitize($ev['descripcion']) ?>

                                        </div>

                                        <?php if ($esPropio): ?>

                                            <div
                                                style="
                                                    font-size:0.7rem;
                                                    color:var(--red);
                                                "
                                            >

                                                <i class="fas fa-lock"></i>

                                                Tu jugador

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                    <div>

                                        <?php if ($ev['resultado'] !== 'pendiente'): ?>

                                            <span class="badge">

                                                <?= sanitize($ev['resultado']) ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="odd-value">

                                                <?= number_format($ev['cuota'], 2) ?>x

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </button>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

        <!-- BETSLIP -->

        <div>

            <?php if ($partido['estado'] === 'abierto'): ?>

                <div class="card" style="position:sticky;top:80px;">

                    <div class="card-header">

                        <span class="card-title">

                            <i class="fas fa-ticket-alt"></i>

                            Tu Apuesta

                        </span>

                        <span
                            id="betslipTipo"
                            class="badge badge-muted"
                        >

                            Simple

                        </span>

                    </div>

                    <div class="card-body">

                        <div
                            id="betslipContainer"
                            style="
                                margin-bottom:14px;
                                min-height:40px;
                            "
                        >

                            <div id="betslipItems">

                                <p
                                    style="
                                        color:var(--text-muted);
                                        font-size:0.85rem;
                                        text-align:center;
                                        padding:20px 0;
                                    "
                                >

                                    <i class="fas fa-hand-pointer"></i>

                                    Selecciona una cuota para apostar

                                </p>

                            </div>

                        </div>

                        <div
                            style="
                                border-top:1px solid var(--border);
                                padding-top:14px;
                                margin-bottom:14px;
                            "
                        >

                            <div class="betslip-row">

                                <span>
                                    Cuota Total
                                </span>

                                <span
                                    class="text-gold fw-bold"
                                    id="betslipCuota"
                                >

                                    1.00

                                </span>

                            </div>

                            <div class="betslip-row ganancia">

                                <span>
                                    Ganancia potencial
                                </span>

                                <span id="betslipGanancia">
                                    —
                                </span>

                            </div>

                        </div>

                        <div class="form-group">

                            <label class="form-label">

                                Monto a apostar (BB)

                            </label>

                            <input
                                type="number"
                                id="betMonto"
                                class="form-control"
                                placeholder="Ej: 5000"
                                min="100"
                                step="100"
                            >

                        </div>

                        <div class="bet-quick-btns">

                            <?php foreach ([1000,5000,10000,25000] as $q): ?>

                                <button
                                    type="button"
                                    class="bet-quick-btn"
                                    onclick="setQuickAmount(<?= $q ?>)"
                                >

                                    <?= formatMoney($q) ?>

                                </button>

                            <?php endforeach; ?>

                        </div>

                        <button
                            type="button"
                            onclick="submitBet()"
                            class="btn btn-primary btn-lg btn-block"
                        >

                            <i class="fas fa-check"></i>

                            Confirmar Apuesta

                        </button>

                        <button
                            type="button"
                            onclick="clearBetslip()"
                            class="btn btn-outline btn-sm btn-block mt-1"
                        >

                            <i class="fas fa-trash"></i>

                            Limpiar selección

                        </button>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal-overlay" id="confirmModal">

    <div class="modal">

        <div class="modal-header">

            <span class="modal-title">

                <i
                    class="fas fa-check-circle"
                    style="color:var(--green)"
                ></i>

                Confirmar Apuesta

            </span>

            <button
                class="modal-close"
                onclick="closeModal('confirmModal')"
            >

                ×

            </button>

        </div>

        <div class="modal-body">

            <div class="betslip-row">

                <span>Tipo:</span>

                <span id="confirmTipo"></span>
                
                <div class="modal-footer" style="padding:16px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end;">

    <button
        type="button"
        class="btn btn-outline"
        onclick="closeModal('confirmModal')"
    >
        Cancelar
    </button>

    <button
        type="button"
        class="btn btn-primary"
        onclick="confirmarApuesta()"
    >
        Confirmar apuesta
    </button>

</div>

            </div>

            <div class="betslip-row">

                <span>Monto:</span>

                <span id="confirmMonto"></span>

            </div>

            <div class="betslip-row">

                <span>Cuota total:</span>

                <span id="confirmCuota"></span>

            </div>

            <div class="betslip-row">

                <span>Ganancia potencial:</span>

                <span id="confirmGanancia"></span>

            </div>

        </div>

    </div>

</div>

<!-- JS EVENTOS -->

<script>

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.odd-btn').forEach(button => {

        button.addEventListener('click', function() {

            if (this.classList.contains('blocked')) {
                return;
            }

            addToBetslip(
                this.dataset.evento,
                this.dataset.partido,
                this.dataset.descripcion,
                this.dataset.cuota,
                this.dataset.jugador
            );

        });

    });

});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>