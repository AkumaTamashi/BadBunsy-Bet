```php id="7v2f4s"
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$user = getCurrentUser();

if ($user['rol'] !== 'admin') {

    die('Acceso denegado');
}

$db = getDB();

// ======================================================
// FILTRO
// ======================================================

$filtro = $_GET['estado'] ?? 'todos';

$sql = "
    SELECT
        a.*,
        u.nombre,
        p.rival
    FROM apuestas a
    INNER JOIN usuarios u
        ON u.id = a.usuario_id
    INNER JOIN partidos p
        ON p.id = a.partido_id
";

$params = [];

if ($filtro !== 'todos') {

    $sql .= " WHERE a.estado = ? ";

    $params[] = $filtro;
}

$sql .= " ORDER BY a.id DESC ";

$stmt = $db->prepare($sql);

$stmt->execute($params);

$apuestas = $stmt->fetchAll();

$pageTitle = 'Apuestas';

include __DIR__ . '/../includes/header.php';

?>

<div class="container">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;
            flex-wrap:wrap;
            gap:15px;
        "
    >

        <h1 class="page-title">

            <i class="fas fa-ticket-alt"></i>

            Gestión de Apuestas

        </h1>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">

            <a
                href="?estado=todos"
                class="btn <?= $filtro === 'todos' ? 'btn-primary' : 'btn-outline' ?>"
            >
                Todas
            </a>

            <a
                href="?estado=pendiente"
                class="btn <?= $filtro === 'pendiente' ? 'btn-primary' : 'btn-outline' ?>"
            >
                Pendientes
            </a>

            <a
                href="?estado=ganada"
                class="btn <?= $filtro === 'ganada' ? 'btn-primary' : 'btn-outline' ?>"
            >
                Ganadas
            </a>

            <a
                href="?estado=perdida"
                class="btn <?= $filtro === 'perdida' ? 'btn-primary' : 'btn-outline' ?>"
            >
                Perdidas
            </a>

        </div>

    </div>

    <?php if (empty($apuestas)): ?>

        <div class="card">

            <div class="card-body">

                No hay apuestas.

            </div>

        </div>

    <?php endif; ?>

    <?php foreach ($apuestas as $apuesta): ?>

        <div class="card mb-3">

            <div class="card-header">

                <div>

                    <strong>

                        Apuesta #<?= $apuesta['id'] ?>

                    </strong>

                    <div style="font-size:0.85rem;color:gray;">

                        <?= sanitize($apuesta['nombre']) ?>

                    </div>

                </div>

                <span
                    class="badge
                    <?= $apuesta['estado'] === 'ganada' ? 'badge-green' : '' ?>
                    <?= $apuesta['estado'] === 'perdida' ? 'badge-red' : '' ?>
                    <?= $apuesta['estado'] === 'pendiente' ? 'badge-yellow' : '' ?>
                "

                >

                    <?= strtoupper($apuesta['estado']) ?>

                </span>

            </div>

            <div class="card-body">

                <div
                    style="
                        display:grid;
                        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                        gap:15px;
                        margin-bottom:20px;
                    "
                >

                    <div>

                        <small style="color:gray;">

                            Partido

                        </small>

                        <div>

                            Bad Bunsy vs
                            <?= sanitize($apuesta['rival']) ?>

                        </div>

                    </div>

                    <div>

                        <small style="color:gray;">

                            Tipo

                        </small>

                        <div>

                            <?= strtoupper($apuesta['tipo']) ?>

                        </div>

                    </div>

                    <div>

                        <small style="color:gray;">

                            Monto

                        </small>

                        <div class="text-gold">

                            <?= number_format($apuesta['monto'],0,',','.') ?> BB

                        </div>

                    </div>

                    <div>

                        <small style="color:gray;">

                            Cuota

                        </small>

                        <div>

                            <?= number_format($apuesta['cuota_total'],2) ?>x

                        </div>

                    </div>

                    <div>

                        <small style="color:gray;">

                            Posible Ganancia

                        </small>

                        <div class="text-green">

                            <?= number_format($apuesta['posible_ganancia'],0,',','.') ?> BB

                        </div>

                    </div>

                    <div>

                        <small style="color:gray;">

                            Fecha

                        </small>

                        <div>

                            <?= date('d/m/Y H:i', strtotime($apuesta['fecha'])) ?>

                        </div>

                    </div>

                </div>

                <div
                    style="
                        background:#111;
                        border-radius:10px;
                        padding:15px;
                    "
                >

                    <strong>

                        Eventos Apostados

                    </strong>

                    <div style="margin-top:15px;">

                        <?php

                        $stmtDetalle = $db->prepare("
                            SELECT
                                e.*
                            FROM apuesta_detalle ad
                            INNER JOIN eventos e
                                ON e.id = ad.evento_id
                            WHERE ad.apuesta_id = ?
                        ");

                        $stmtDetalle->execute([$apuesta['id']]);

                        $eventos = $stmtDetalle->fetchAll();

                        ?>

                        <?php foreach ($eventos as $evento): ?>

                            <div
                                style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    padding:10px;
                                    border-bottom:1px solid #222;
                                "
                            >

                                <div>

                                    <?= sanitize($evento['descripcion']) ?>

                                </div>

                                <div
                                    style="
                                        display:flex;
                                        gap:10px;
                                        align-items:center;
                                    "
                                >

                                    <span class="text-gold">

                                        <?= number_format($evento['cuota'],2) ?>x

                                    </span>

                                    <span
                                        class="badge
                                        <?= $evento['resultado'] === 'ganada' ? 'badge-green' : '' ?>
                                        <?= $evento['resultado'] === 'perdida' ? 'badge-red' : '' ?>
                                        <?= $evento['resultado'] === 'pendiente' ? 'badge-yellow' : '' ?>
                                    "
                                    >

                                        <?= strtoupper($evento['resultado']) ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
```
