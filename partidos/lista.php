<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$filter = sanitize($_GET['estado'] ?? 'todos');

$sql = "SELECT p.*, COUNT(e.id) as num_eventos FROM partidos p 
        LEFT JOIN eventos e ON e.partido_id = p.id";
if ($filter !== 'todos') {
    $sql .= " WHERE p.estado = :estado";
}
$sql .= " GROUP BY p.id ORDER BY p.fecha DESC";

$stmt = $db->prepare($sql);
if ($filter !== 'todos') $stmt->execute(['estado' => $filter]);
else $stmt->execute();
$partidos = $stmt->fetchAll();

$pageTitle = 'Partidos';
include __DIR__ . '/../includes/header.php';
?>
<meta name="site-url" content="<?= SITE_URL ?>">
<meta name="jugador-asociado" content="<?= getCurrentUser()['jugador_asociado'] ?>">

<div class="container">
    <div class="page-header d-flex align-center gap-2" style="justify-content:space-between;flex-wrap:wrap;">
        <div>
            <h1 class="page-title"><i class="fas fa-futbol"></i> Partidos</h1>
            <p class="page-subtitle">Todos los partidos del club Bad Bunsy</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach (['todos','abierto','cerrado','finalizado'] as $s): ?>
            <a href="?estado=<?= $s ?>" class="btn btn-sm <?= $filter === $s ? 'btn-primary' : 'btn-outline' ?>">
                <?= ucfirst($s) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($partidos)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-futbol"></i>
            <p>No hay partidos <?= $filter !== 'todos' ? "con estado '$filter'" : '' ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="grid-2">
        <?php foreach ($partidos as $p): ?>
        <div class="match-card">
            <div class="match-header">
                <div>
                    <div class="match-teams">Bad Bunsy <span class="match-vs">VS</span> <?= sanitize($p['rival']) ?></div>
                    <div class="match-date">
                        <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($p['fecha'])) ?>
                        · <i class="fas fa-clock"></i> <?= substr($p['hora'] ?? '00:00', 0, 5) ?>
                    </div>
                </div>
                <span class="match-status status-<?= $p['estado'] ?>"><?= $p['estado'] ?></span>
            </div>
            <div class="match-body">
                <?php if ($p['descripcion']): ?>
                <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:10px;"><?= sanitize($p['descripcion']) ?></p>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">
                        <i class="fas fa-list"></i> <?= $p['num_eventos'] ?> mercados
                    </span>
                    <?php if ($p['estado'] === 'abierto'): ?>
                    <a href="<?= SITE_URL ?>/partidos/ver.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">
                        Apostar <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php else: ?>
                    <a href="<?= SITE_URL ?>/partidos/ver.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">
                        Ver detalles
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>