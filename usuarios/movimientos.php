<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$user = getCurrentUser();

$movimientos = $db->prepare("SELECT * FROM movimientos WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 100");
$movimientos->execute([$user['id']]);
$movimientos = $movimientos->fetchAll();

$pageTitle = 'Movimientos';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-history"></i> Historial de Movimientos</h1>
        <p class="page-subtitle">Todos tus movimientos de saldo</p>
    </div>

    <div class="card">
        <?php if (empty($movimientos)): ?>
        <div class="empty-state"><i class="fas fa-history"></i><p>No tienes movimientos aún</p></div>
        <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                    <?php
                    $isPositive = in_array($m['tipo'], ['ganancia', 'deposito', 'ajuste']);
                    $icons = ['apuesta'=>'fa-ticket-alt','ganancia'=>'fa-trophy','deposito'=>'fa-plus-circle','retiro'=>'fa-minus-circle','ajuste'=>'fa-edit'];
                    ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                        <td>
                            <span class="badge <?= $isPositive ? 'badge-green' : 'badge-red' ?>">
                                <i class="fas <?= $icons[$m['tipo']] ?? 'fa-circle' ?>"></i> <?= ucfirst($m['tipo']) ?>
                            </span>
                        </td>
                        <td><?= sanitize($m['descripcion'] ?? '—') ?></td>
                        <td class="fw-bold <?= $isPositive ? 'text-green' : 'text-red' ?>">
                            <?= $isPositive ? '+' : '-' ?><?= formatMoney($m['monto']) ?> BB
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>