<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

$filtroEstado  = sanitize($_GET['estado']  ?? 'todos');
$filtroPartido = intval($_GET['partido']   ?? 0);
$buscar        = sanitize($_GET['q']       ?? '');
$pagina        = max(1, intval($_GET['pag'] ?? 1));
$porPagina     = 25;
$offset        = ($pagina - 1) * $porPagina;

$sql = "SELECT a.*, u.nombre as usuario_nombre, u.jugador_asociado, p.rival
        FROM apuestas a
        JOIN usuarios u ON u.id = a.usuario_id
        JOIN partidos p ON p.id = a.partido_id
        WHERE 1=1";
$params = [];

if ($filtroEstado !== 'todos') {
    $sql .= " AND a.estado = ?"; $params[] = $filtroEstado;
}
if ($filtroPartido > 0) {
    $sql .= " AND a.partido_id = ?"; $params[] = $filtroPartido;
}
if ($buscar) {
    $sql .= " AND (u.nombre LIKE ? OR p.rival LIKE ?)";
    $params[] = "%$buscar%"; $params[] = "%$buscar%";
}

// Total para paginación
$countStmt = $db->prepare("SELECT COUNT(*) FROM ($sql) t");
$countStmt->execute($params);
$totalApuestas = $countStmt->fetchColumn();
$totalPaginas  = ceil($totalApuestas / $porPagina);

$sql .= " ORDER BY a.fecha DESC LIMIT $porPagina OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$apuestas = $stmt->fetchAll();

// Partidos para filtro
$partidos = $db->query("SELECT id, rival, fecha FROM partidos ORDER BY fecha DESC")->fetchAll();

// Stats globales
$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado='ganada' THEN 1 ELSE 0 END) as ganadas,
    SUM(CASE WHEN estado='perdida' THEN 1 ELSE 0 END) as perdidas,
    SUM(monto) as volumen,
    SUM(CASE WHEN estado='ganada' THEN posible_ganancia ELSE 0 END) as pagado
FROM apuestas")->fetch();

$pageTitle = 'Ver Apuestas';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-ticket-alt"></i> Todas las Apuestas</h1>
        <p class="page-subtitle"><?= number_format($totalApuestas) ?> apuestas encontradas</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total apuestas</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-clock"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['pendientes'] ?></div><div class="stat-label">Pendientes</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-trophy"></i></div>
            <div class="stat-info"><div class="stat-value"><?= $stats['ganadas'] ?></div><div class="stat-label">Ganadas</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-coins"></i></div>
            <div class="stat-info"><div class="stat-value"><?= formatMoney($stats['volumen']??0) ?></div><div class="stat-label">Volumen total (BB)</div></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:14px 20px;">
            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <div>
                    <label class="form-label" style="font-size:.75rem;">Buscar usuario/partido</label>
                    <input type="text" name="q" class="form-control" style="width:200px;"
                           placeholder="Nombre…" value="<?= sanitize($buscar) ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size:.75rem;">Estado</label>
                    <select name="estado" class="form-control" style="width:140px;">
                        <?php foreach (['todos','pendiente','ganada','perdida','cancelada'] as $e): ?>
                        <option value="<?= $e ?>" <?= $filtroEstado===$e?'selected':'' ?>><?= ucfirst($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.75rem;">Partido</label>
                    <select name="partido" class="form-control" style="width:200px;">
                        <option value="0">Todos los partidos</option>
                        <?php foreach ($partidos as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filtroPartido===$p['id']?'selected':'' ?>>
                            vs <?= sanitize($p['rival']) ?> (<?= date('d/m/Y',strtotime($p['fecha'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:6px;align-self:flex-end;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="?" class="btn btn-outline btn-sm">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <?php if (empty($apuestas)): ?>
        <div class="empty-state" style="padding:40px;">
            <i class="fas fa-ticket-alt"></i><p>No hay apuestas con estos filtros</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Usuario</th>
                        <th>Partido</th>
                        <th>Tipo</th>
                        <th>Eventos</th>
                        <th>Monto</th>
                        <th>Cuota</th>
                        <th>G. Potencial</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apuestas as $ap):
                        $badgeClass = ['ganada'=>'badge-green','perdida'=>'badge-red','pendiente'=>'badge-gold','cancelada'=>'badge-muted'][$ap['estado']] ?? 'badge-muted';
                        // Eventos de la apuesta
                        $evs = $db->prepare("SELECT e.descripcion, e.cuota, e.resultado FROM apuesta_detalle ad JOIN eventos e ON e.id=ad.evento_id WHERE ad.apuesta_id=?");
                        $evs->execute([$ap['id']]); $evs = $evs->fetchAll();
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);font-size:.8rem;">#<?= $ap['id'] ?></td>
                        <td>
                            <div style="font-weight:600;font-size:.85rem;"><?= sanitize($ap['usuario_nombre']) ?></div>
                            <div style="font-size:.72rem;color:var(--text-muted);"><?= sanitize($ap['jugador_asociado']) ?></div>
                        </td>
                        <td style="font-size:.85rem;">vs <?= sanitize($ap['rival']) ?></td>
                        <td><span class="badge <?= $ap['tipo']==='combinada'?'badge-blue':'badge-muted' ?>"><?= ucfirst($ap['tipo']) ?></span></td>
                        <td style="font-size:.75rem;max-width:180px;">
                            <?php foreach ($evs as $ev):
                                $rBadge = ['pendiente'=>'badge-gold','ganada'=>'badge-green','perdida'=>'badge-red'][$ev['resultado']]??'badge-muted';
                            ?>
                            <div style="margin-bottom:2px;">
                                <span class="badge <?= $rBadge ?>" style="font-size:.6rem;"><?= substr($ev['resultado'],0,1) ?></span>
                                <?= sanitize($ev['descripcion']) ?> <span style="color:var(--gold);">(<?= number_format($ev['cuota'],2) ?>x)</span>
                            </div>
                            <?php endforeach; ?>
                        </td>
                        <td class="fw-bold"><?= formatMoney($ap['monto']) ?></td>
                        <td class="text-gold fw-bold"><?= number_format($ap['cuota_total'],2) ?>x</td>
                        <td class="<?= $ap['estado']==='ganada'?'text-green fw-bold':'' ?>"><?= formatMoney($ap['posible_ganancia']) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= $ap['estado'] ?></span></td>
                        <td style="font-size:.78rem;white-space:nowrap;color:var(--text-muted);"><?= date('d/m/Y H:i',strtotime($ap['fecha'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;padding:16px;flex-wrap:wrap;">
            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <a href="?pag=<?= $p ?>&estado=<?= $filtroEstado ?>&partido=<?= $filtroPartido ?>&q=<?= urlencode($buscar) ?>"
               class="btn btn-sm <?= $p===$pagina?'btn-primary':'btn-outline' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>