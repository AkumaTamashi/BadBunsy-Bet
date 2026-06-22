<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Acciones rápidas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = sanitize($_POST['accion'] ?? '');
    $partido_id = intval($_POST['partido_id'] ?? 0);

    if ($partido_id > 0) {
        if ($accion === 'cerrar') {
            $db->prepare("UPDATE partidos SET estado='cerrado' WHERE id=?")->execute([$partido_id]);
            flashMessage('success', 'Partido cerrado. Ya no se aceptan apuestas.');
        } elseif ($accion === 'abrir') {
            $db->prepare("UPDATE partidos SET estado='abierto' WHERE id=?")->execute([$partido_id]);
            flashMessage('success', 'Partido abierto para apuestas.');
        } elseif ($accion === 'eliminar') {
            $db->prepare("DELETE FROM partidos WHERE id=?")->execute([$partido_id]);
            flashMessage('success', 'Partido eliminado.');
        }
    }
    header('Location: ' . SITE_URL . '/admin/partidos.php');
    exit();
}

$partidos = $db->query("SELECT p.*, COUNT(DISTINCT e.id) as num_eventos, COUNT(DISTINCT a.id) as num_apuestas 
    FROM partidos p 
    LEFT JOIN eventos e ON e.partido_id = p.id 
    LEFT JOIN apuestas a ON a.partido_id = p.id
    GROUP BY p.id ORDER BY p.fecha DESC")->fetchAll();

$pageTitle = 'Gestionar Partidos';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="admin-main">
    <div class="page-header d-flex align-center gap-2" style="justify-content:space-between;">
        <div>
            <h1 class="page-title"><i class="fas fa-futbol"></i> Gestionar Partidos</h1>
        </div>
        <a href="<?= SITE_URL ?>/admin/crear_partido.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Partido
        </a>
    </div>

    <div class="card">
        <?php if (empty($partidos)): ?>
        <div class="empty-state"><i class="fas fa-futbol"></i><p>No hay partidos creados</p></div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Partido</th><th>Fecha</th><th>Estado</th><th>Mercados</th><th>Apuestas</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($partidos as $p): ?>
                    <tr>
                        <td class="fw-bold">Bad Bunsy vs <?= sanitize($p['rival']) ?></td>
                        <td style="white-space:nowrap;"><?= date('d/m/Y', strtotime($p['fecha'])) ?> <?= substr($p['hora'] ?? '', 0, 5) ?></td>
                        <td><span class="match-status status-<?= $p['estado'] ?>"><?= $p['estado'] ?></span></td>
                        <td><?= $p['num_eventos'] ?></td>
                        <td><?= $p['num_apuestas'] ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if ($p['estado'] === 'abierto'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="accion" value="cerrar">
                                    <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('¿Cerrar apuestas?')">
                                        <i class="fas fa-lock"></i> Cerrar
                                    </button>
                                </form>
                                <?php elseif ($p['estado'] === 'cerrado'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="accion" value="abrir">
                                    <button class="btn btn-sm btn-primary" type="submit">
                                        <i class="fas fa-unlock"></i> Abrir
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($p['estado'] !== 'finalizado'): ?>
                                <a href="<?= SITE_URL ?>/admin/resultados.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-gold">
                                    <i class="fas fa-check"></i> Resultados
                                </a>
                                <?php endif; ?>
                                
                                <a href="<?= SITE_URL ?>/admin/editar_partido.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($p['num_apuestas'] == 0): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="partido_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('¿Eliminar partido? Esta acción no se puede deshacer.')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>