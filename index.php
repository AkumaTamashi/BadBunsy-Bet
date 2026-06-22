<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();
//AKUMA TAMASHI
// Stats del usuario
$apuestas = $db->prepare("SELECT COUNT(*) as total, 
    SUM(CASE WHEN estado='ganada' THEN 1 ELSE 0 END) as ganadas,
    SUM(CASE WHEN estado='perdida' THEN 1 ELSE 0 END) as perdidas,
    SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado='ganada' THEN posible_ganancia ELSE 0 END) as total_ganado,
    SUM(monto) as total_apostado
    FROM apuestas WHERE usuario_id = ?");
$apuestas->execute([$user['id']]);
$stats = $apuestas->fetch();

// Partidos abiertos
$partidos = $db->query("SELECT p.*, COUNT(e.id) as num_eventos FROM partidos p 
    LEFT JOIN eventos e ON e.partido_id = p.id
    WHERE p.estado = 'abierto' 
    GROUP BY p.id
    ORDER BY p.fecha ASC LIMIT 5")->fetchAll();

// Últimas apuestas del usuario
$ultApuestas = $db->prepare("SELECT a.*, p.rival, p.fecha FROM apuestas a 
    JOIN partidos p ON p.id = a.partido_id 
    WHERE a.usuario_id = ? 
    ORDER BY a.fecha DESC LIMIT 5");
$ultApuestas->execute([$user['id']]);
$ultimasApuestas = $ultApuestas->fetchAll();

// Ranking top 5
$ranking = $db->query("SELECT nombre, saldo, jugador_asociado FROM usuarios 
    WHERE rol = 'usuario' AND activo = 1 
    ORDER BY saldo DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">
<meta name="jugador-asociado" content="<?= $user['jugador_asociado'] ?>">

<div class="container">
    <!-- Bienvenida -->
    <div class="page-header d-flex align-center gap-2" style="flex-wrap:wrap;justify-content:space-between;">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home"></i>
                Hola, <?= sanitize($user['nombre']) ?>
            </h1>
            <p class="page-subtitle">Jugador asociado: <strong class="text-green"><?= $user['jugador_asociado'] ?></strong> · Club Bad Bunsy</p>
        </div>
        <div class="nav-balance" style="font-size:1.1rem;padding:12px 20px;">
            <i class="fas fa-coins"></i>
            <span><?= formatMoney($user['saldo']) ?> BB</span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= formatMoney($user['saldo']) ?></div>
                <div class="stat-label">Saldo disponible (BB)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-trophy"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['ganadas'] ?? 0 ?></div>
                <div class="stat-label">Apuestas ganadas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['perdidas'] ?? 0 ?></div>
                <div class="stat-label">Apuestas perdidas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['pendientes'] ?? 0 ?></div>
                <div class="stat-label">Apuestas pendientes</div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Partidos disponibles -->
        <div>
            <div class="page-header">
                <h2 class="page-title" style="font-size:1.1rem;">
                    <i class="fas fa-futbol"></i> Partidos Disponibles
                </h2>
            </div>

            <?php if (empty($partidos)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-futbol"></i>
                    <p>No hay partidos abiertos para apostar</p>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($partidos as $partido): ?>
            <div class="match-card mb-2">
                <div class="match-header">
                    <div>
                        <div class="match-teams">
                            Bad Bunsy <span class="match-vs">VS</span> <?= sanitize($partido['rival']) ?>
                        </div>
                        <div class="match-date">
                            <i class="fas fa-calendar"></i> 
                            <?= date('d/m/Y H:i', strtotime($partido['fecha'] . ' ' . ($partido['hora'] ?? '00:00'))) ?>
                        </div>
                    </div>
                    <span class="match-status status-<?= $partido['estado'] ?>"><?= $partido['estado'] ?></span>
                </div>
                <div class="match-body">
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:10px;">
                        <i class="fas fa-list"></i> <?= $partido['num_eventos'] ?> mercados disponibles
                    </p>
                    <a href="<?= SITE_URL ?>/partidos/ver.php?id=<?= $partido['id'] ?>" class="btn btn-primary btn-sm">
                        Ver y Apostar <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <a href="<?= SITE_URL ?>/partidos/lista.php" class="btn btn-outline btn-sm">Ver todos los partidos</a>
            <?php endif; ?>
        </div>

        <!-- Sidebar: últimas apuestas + ranking -->
        <div>
            <!-- Últimas apuestas -->
            <div class="card mb-2">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-ticket-alt"></i> Últimas Apuestas</span>
                    <a href="<?= SITE_URL ?>/apuestas/mis_apuestas.php" class="btn btn-outline btn-sm">Ver todas</a>
                </div>
                <?php if (empty($ultimasApuestas)): ?>
                <div class="card-body">
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-ticket-alt"></i>
                        <p>Aún no has apostado</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Partido</th>
                                <th>Monto</th>
                                <th>Cuota</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasApuestas as $ap): ?>
                            <tr>
                                <td>
                                    <div style="font-size:0.8rem;">vs <?= sanitize($ap['rival']) ?></div>
                                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= date('d/m', strtotime($ap['fecha'])) ?></div>
                                </td>
                                <td class="fw-bold"><?= formatMoney($ap['monto']) ?></td>
                                <td class="text-gold"><?= number_format($ap['cuota_total'], 2) ?>x</td>
                                <td>
                                    <?php
                                    $badgeClass = ['ganada'=>'badge-green','perdida'=>'badge-red','pendiente'=>'badge-gold','cancelada'=>'badge-muted'][$ap['estado']] ?? 'badge-muted';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $ap['estado'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mini Ranking -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-trophy"></i> Top Apostadores</span>
                    <a href="<?= SITE_URL ?>/usuarios/ranking.php" class="btn btn-outline btn-sm">Ver ranking</a>
                </div>
                <?php foreach ($ranking as $i => $u): ?>
                <div class="ranking-item">
                    <div class="ranking-pos <?= $i === 0 ? 'pos-1' : ($i === 1 ? 'pos-2' : ($i === 2 ? 'pos-3' : 'pos-other')) ?>"><?= $i+1 ?></div>
                    <div class="ranking-user">
                        <div class="ranking-name"><?= sanitize($u['nombre']) ?></div>
                        <div class="ranking-jugador"><?= $u['jugador_asociado'] ?></div>
                    </div>
                    <div class="ranking-saldo"><?= formatMoney($u['saldo']) ?> BB</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
