<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
$filter = sanitize($_GET['estado'] ?? 'todas');

$sql = "SELECT a.*, p.rival, p.fecha as fecha_partido FROM apuestas a 
        JOIN partidos p ON p.id = a.partido_id 
        WHERE a.usuario_id = ?";
$params = [$user['id']];

if ($filter !== 'todas') {
    $sql .= " AND a.estado = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY a.fecha DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$apuestas = $stmt->fetchAll();

// Stats
$statsQ = $db->prepare("SELECT 
    SUM(monto) as total_apostado,
    SUM(CASE WHEN estado='ganada' THEN posible_ganancia ELSE 0 END) as total_ganado,
    SUM(CASE WHEN estado='perdida' THEN monto ELSE 0 END) as total_perdido
    FROM apuestas WHERE usuario_id = ?");
$statsQ->execute([$user['id']]);
$stats = $statsQ->fetch();

$pageTitle = 'Mis Apuestas';
include __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">

<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-ticket-alt"></i> Mis Apuestas</h1>
        <p class="page-subtitle">Historial completo de tus apuestas</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= formatMoney($stats['total_apostado'] ?? 0) ?></div>
                <div class="stat-label">Total apostado (BB)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= formatMoney($stats['total_ganado'] ?? 0) ?></div>
                <div class="stat-label">Total ganado (BB)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= formatMoney($stats['total_perdido'] ?? 0) ?></div>
                <div class="stat-label">Total perdido (BB)</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
        <?php foreach (['todas','pendiente','ganada','perdida'] as $s): ?>
        <a href="?estado=<?= $s ?>" class="btn btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-outline' ?>">
            <?= ucfirst($s) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabla -->
    <div class="card">
        <?php if (empty($apuestas)): ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <p>No tienes apuestas <?= $filter !== 'todas' ? "con estado '$filter'" : 'aún' ?></p>
            <a href="<?= SITE_URL ?>/partidos/lista.php" class="btn btn-primary btn-sm mt-1">Ver partidos disponibles</a>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Partido</th>
                        <th>Fecha apuesta</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Cuota</th>
                        <th>Posible ganancia</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apuestas as $ap): ?>
                    <?php
                    $eventos_ap = $db->prepare("SELECT e.descripcion, e.cuota FROM apuesta_detalle ad JOIN eventos e ON e.id = ad.evento_id WHERE ad.apuesta_id = ?");
                    $eventos_ap->execute([$ap['id']]);
                    $ev_list = $eventos_ap->fetchAll();
                    $badgeClass = ['ganada'=>'badge-green','perdida'=>'badge-red','pendiente'=>'badge-gold','cancelada'=>'badge-muted'][$ap['estado']] ?? 'badge-muted';
                    ?>
                    <tr>
                        <td class="text-muted">#<?= $ap['id'] ?></td>
                        <td>
                            <strong>Bad Bunsy vs <?= sanitize($ap['rival']) ?></strong>
                            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">
                                <?php foreach ($ev_list as $ev): ?>
                                <span style="display:block;">· <?= sanitize($ev['descripcion']) ?> (<?= number_format($ev['cuota'],2) ?>x)</span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($ap['fecha'])) ?></td>
                        <td>
                            <span class="badge <?= $ap['tipo'] === 'combinada' ? 'badge-blue' : 'badge-muted' ?>">
                                <?= ucfirst($ap['tipo']) ?>
                            </span>
                        </td>
                        <td class="fw-bold"><?= formatMoney($ap['monto']) ?></td>
                        <td class="text-gold fw-bold"><?= number_format($ap['cuota_total'], 2) ?>x</td>
                        <td class="<?= $ap['estado'] === 'ganada' ? 'text-green' : '' ?> fw-bold">
                            <?= formatMoney($ap['posible_ganancia']) ?>
                        </td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $ap['estado'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>