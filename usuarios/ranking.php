<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();

$ranking = $db->query("SELECT u.nombre, u.saldo, u.jugador_asociado,
    COUNT(a.id) as total_apuestas,
    SUM(CASE WHEN a.estado='ganada' THEN 1 ELSE 0 END) as ganadas,
    SUM(CASE WHEN a.estado='perdida' THEN 1 ELSE 0 END) as perdidas
    FROM usuarios u
    LEFT JOIN apuestas a ON a.usuario_id = u.id
    WHERE u.rol = 'usuario' AND u.activo = 1
    GROUP BY u.id, u.nombre, u.saldo, u.jugador_asociado
    ORDER BY u.saldo DESC")->fetchAll();

$pageTitle = 'Ranking';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-trophy"></i> Ranking de Apostadores</h1>
        <p class="page-subtitle">¿Quién domina el club Bad Bunsy?</p>
    </div>

    <div class="grid-2">
        <!-- Ranking por saldo -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-coins"></i> Por Saldo Actual</span>
            </div>
            <?php if (empty($ranking)): ?>
            <div class="empty-state"><p>No hay usuarios registrados</p></div>
            <?php else: ?>
            <?php foreach ($ranking as $i => $u): ?>
            <div class="ranking-item">
                <div class="ranking-pos <?= $i === 0 ? 'pos-1' : ($i === 1 ? 'pos-2' : ($i === 2 ? 'pos-3' : 'pos-other')) ?>">
                    <?= $i === 0 ? '👑' : ($i+1) ?>
                </div>
                <div class="ranking-user" style="flex:1;">
                    <div class="ranking-name"><?= sanitize($u['nombre']) ?></div>
                    <div class="ranking-jugador">
                        <?= $u['jugador_asociado'] ?> · 
                        <?= $u['ganadas'] ?> ganadas / <?= $u['perdidas'] ?> perdidas
                    </div>
                </div>
                <div>
                    <div class="ranking-saldo"><?= formatMoney($u['saldo']) ?> BB</div>
                    <?php if ($u['total_apuestas'] > 0): ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);text-align:right;">
                        <?= $u['total_apuestas'] ?> apuestas
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Stats del club -->
        <div>
            <div class="card mb-2">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-chart-bar"></i> Jugadores del Club</span>
                </div>
                <div class="card-body">
                    <?php $jugadores = ['Alex' => 'DC', 'Rizzo' => 'MCO', 'Andrew' => 'ED', 'Yamato' => 'MCE', 'Emanuel' => 'ED Derecho']; ?>
                    <?php foreach ($jugadores as $jug => $pos): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);">
                        <div>
                            <div style="font-weight:600;"><?= $jug ?></div>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= $pos ?></div>
                        </div>
                        <span class="badge badge-blue"><?= $pos ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            $global = $db->query("SELECT COUNT(*) as total, 
                SUM(CASE WHEN estado='ganada' THEN 1 ELSE 0 END) as ganadas,
                SUM(monto) as volumen
                FROM apuestas")->fetch();
            ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-globe"></i> Estadísticas Globales</span>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div style="text-align:center;padding:16px;background:var(--bg-hover);border-radius:8px;">
                            <div style="font-size:1.5rem;font-weight:800;color:var(--green);"><?= $global['total'] ?? 0 ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted);">Apuestas totales</div>
                        </div>
                        <div style="text-align:center;padding:16px;background:var(--bg-hover);border-radius:8px;">
                            <div style="font-size:1.5rem;font-weight:800;color:var(--gold);"><?= $global['ganadas'] ?? 0 ?></div>
                            <div style="font-size:0.78rem;color:var(--text-muted);">Apuestas ganadas</div>
                        </div>
                        <div style="text-align:center;padding:16px;background:var(--bg-hover);border-radius:8px;grid-column:1/-1;">
                            <div style="font-size:1.5rem;font-weight:800;color:var(--blue);"><?= formatMoney($global['volumen'] ?? 0) ?> BB</div>
                            <div style="font-size:0.78rem;color:var(--text-muted);">Volumen total apostado</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>