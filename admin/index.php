<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

$stats = $db->query("SELECT 
    (SELECT COUNT(*) FROM usuarios WHERE rol='usuario') as total_usuarios,
    (SELECT COUNT(*) FROM partidos) as total_partidos,
    (SELECT COUNT(*) FROM apuestas) as total_apuestas,
    (SELECT COUNT(*) FROM apuestas WHERE estado='pendiente') as apuestas_pendientes,
    (SELECT SUM(monto) FROM apuestas) as volumen_total,
    (SELECT COUNT(*) FROM partidos WHERE estado='abierto') as partidos_abiertos
")->fetch();

$ultApuestas = $db->query("SELECT a.*, u.nombre as usuario, p.rival FROM apuestas a 
    JOIN usuarios u ON u.id = a.usuario_id 
    JOIN partidos p ON p.id = a.partido_id
    ORDER BY a.fecha DESC LIMIT 10")->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="admin-main">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Panel de Administración</h1>
        <p class="page-subtitle">Control total de Bad Bunsy Bet</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['total_usuarios'] ?></div><div class="stat-label">Usuarios registrados</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-futbol"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['partidos_abiertos'] ?></div><div class="stat-label">Partidos abiertos</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['apuestas_pendientes'] ?></div><div class="stat-label">Apuestas pendientes</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-coins"></i></div>
            <div class="stat-info"><div class="stat-value"><?= formatMoney($stats['volumen_total'] ?? 0) ?></div><div class="stat-label">Volumen total (BB)</div></div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div class="card mb-3">
        <div class="card-header"><span class="card-title"><i class="fas fa-bolt"></i> Acciones Rápidas</span></div>
        <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
            <a href="<?= SITE_URL ?>/admin/crear_partido.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Partido</a>
            <a href="<?= SITE_URL ?>/admin/resultados.php" class="btn btn-gold"><i class="fas fa-check-double"></i> Cargar Resultados</a>
            <a href="<?= SITE_URL ?>/admin/usuarios.php" class="btn btn-outline"><i class="fas fa-users"></i> Gestionar Usuarios</a>
            <a href="<?= SITE_URL ?>/admin/apuestas.php" class="btn btn-outline"><i class="fas fa-ticket-alt"></i> Ver Apuestas</a>
        </div>
    </div>

    <!-- Últimas apuestas -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-ticket-alt"></i> Últimas Apuestas</span></div>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>#</th><th>Usuario</th><th>Partido</th><th>Tipo</th><th>Monto</th><th>Cuota</th><th>Ganancia pot.</th><th>Estado</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($ultApuestas as $ap): ?>
                    <?php $badgeClass = ['ganada'=>'badge-green','perdida'=>'badge-red','pendiente'=>'badge-gold','cancelada'=>'badge-muted'][$ap['estado']] ?? 'badge-muted'; ?>
                    <tr>
                        <td>#<?= $ap['id'] ?></td>
                        <td class="fw-bold"><?= sanitize($ap['usuario']) ?></td>
                        <td>vs <?= sanitize($ap['rival']) ?></td>
                        <td><span class="badge badge-blue"><?= ucfirst($ap['tipo']) ?></span></td>
                        <td><?= formatMoney($ap['monto']) ?></td>
                        <td class="text-gold"><?= number_format($ap['cuota_total'],2) ?>x</td>
                        <td><?= formatMoney($ap['posible_ganancia']) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $ap['estado'] ?></span></td>
                        <td style="white-space:nowrap;"><?= date('d/m H:i', strtotime($ap['fecha'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>