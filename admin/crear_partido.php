<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db       = getDB();
$error    = '';
$jugadores = getJugadoresActivos(); // ← dinámico

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rival       = sanitize($_POST['rival']        ?? '');
    $fecha       = $_POST['fecha']                 ?? '';
    $hora        = $_POST['hora']                  ?? '20:00';
    $descripcion = sanitize($_POST['descripcion']  ?? '');
    $estado      = sanitize($_POST['estado']       ?? 'abierto');
    $ev_desc     = $_POST['ev_desc']               ?? [];
    $ev_cuota    = $_POST['ev_cuota']              ?? [];
    $ev_jugador  = $_POST['ev_jugador']            ?? [];

    if (empty($rival) || empty($fecha)) {
        $error = 'El rival y la fecha son obligatorios.';
    } else {
        $db->prepare("INSERT INTO partidos (rival, fecha, hora, descripcion, estado) VALUES (?,?,?,?,?)")
           ->execute([$rival, $fecha, $hora, $descripcion, $estado]);
        $partidoId = $db->lastInsertId();

        $stmtEv = $db->prepare("INSERT INTO eventos (partido_id, descripcion, cuota, jugador_relacionado) VALUES (?,?,?,?)");
        for ($i = 0; $i < count($ev_desc); $i++) {
            $desc    = sanitize($ev_desc[$i]  ?? '');
            $cuota   = floatval($ev_cuota[$i] ?? 1.5);
            $jugador = sanitize($ev_jugador[$i] ?? 'ninguno');
            if (!empty($desc) && $cuota > 1.0) {
                $stmtEv->execute([$partidoId, $desc, $cuota, $jugador]);
            }
        }

        flashMessage('success', 'Partido "Bad Bunsy vs ' . $rival . '" creado exitosamente.');
        header('Location: ' . SITE_URL . '/admin/partidos.php');
        exit();
    }
}

// Generar eventos predefinidos usando jugadores dinámicos
$predefinidos = [

    // =========================
    // RESULTADO DEL PARTIDO
    // =========================
    ['Bad Bunsy gana el partido', 3.50, 'ninguno'],
    ['Bad Bunsy pierde el partido', 1.80, 'ninguno'],
    ['Empate en el partido', 5.20, 'ninguno'],

    // =========================
    // GOLES GENERALES
    // =========================
    ['Más de 3.5 goles en el partido', 2.90, 'ninguno'],
    ['Menos de 2.5 goles en el partido', 3.10, 'ninguno'],

    // =========================
    // ALEX
    // =========================
    ['Alex marca gol', 1.50, 'ninguno'],
    ['Alex marca doblete', 2.80, 'Alex'],
    ['Alex marca hat-trick', 5.00, 'Alex'],
    ['Alex asiste un gol', 3.40, 'Alex'],
    ['Alex marca o asiste', 2.20, 'Alex'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Alex recibe tarjeta amarilla', 1.70, 'Alex'],
    ['Alex recibe tarjeta roja', 4.50, 'Alex'],

    // =========================
    // RIZZO
    // =========================
    ['Rizzo marca gol', 2.00, 'ninguno'],
    ['Rizzo marca doblete', 3.20, 'Rizzo'],
    ['Rizzo asiste un gol', 2.60, 'Rizzo'],
    ['Rizzo marca o asiste', 1.70, 'Rizzo'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Rizzo recibe tarjeta amarilla', 1.65, 'Rizzo'],
    ['Rizzo recibe tarjeta roja', 4.30, 'Rizzo'],

    // =========================
    // ANDREW
    // =========================
    ['Andrew marca gol', 4.10, 'ninguno'],
    ['Andrew marca doblete', 7.50, 'Andrew'],
    ['Andrew asiste un gol', 3.80, 'Andrew'],
    ['Andrew marca o asiste', 2.40, 'Andrew'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Andrew recibe tarjeta amarilla', 1.75, 'Andrew'],
    ['Andrew recibe tarjeta roja', 4.60, 'Andrew'],

    // =========================
    // YAMATO
    // =========================
    ['Yamato MVP del partido', 5.00, 'Yamato'],
    ['Yamato marca gol', 3.90, 'ninguno'],
    ['Yamato marca doblete', 7.00, 'Yamato'],
    ['Yamato asiste un gol', 3.70, 'Yamato'],
    ['Yamato marca o asiste', 2.30, 'Yamato'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Yamato recibe tarjeta amarilla', 1.60, 'Yamato'],
    ['Yamato recibe tarjeta roja', 3.80, 'Yamato'],

    // =========================
    // EMANUEL
    // =========================
    ['Emanuel marca gol', 4.20, 'ninguno'],
    ['Emanuel marca doblete', 5.60, 'Emanuel'],
    ['Emanuel asiste un gol', 3.70, 'Emanuel'],
    ['Emanuel marca o asiste', 2.50, 'Emanuel'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Emanuel recibe tarjeta amarilla', 1.68, 'Emanuel'],
    ['Emanuel recibe tarjeta roja', 3.40, 'Emanuel'],
    
    // =========================
    // Antrax
    // =========================
    ['Antrax marca gol', 5.20, 'ninguno'],
    ['Antrax marca doblete', 5.60, 'Antrax'],
    ['Antrax asiste un gol', 2.70, 'Antrax'],
    ['Antrax marca o asiste', 1.50, 'Antrax'],

    // 🔻 Tarjetas más probables (AJUSTADAS)
    ['Antrax recibe tarjeta amarilla', 1.68, 'Antrax'],
    ['Antrax recibe tarjeta roja', 3.40, 'Antrax'],
];
// Añadir un evento por cada jugador activo
//foreach ($jugadores as $j) {
//    $predefinidos[] = [$j . ' marca gol', 3.50, $j];
//}

$pageTitle = 'Crear Partido';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-plus-circle"></i> Crear Nuevo Partido</h1>
    </div>

    <?php if ($error): ?>
    <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="grid-2">

            <!-- Info del partido -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-futbol"></i> Información del Partido</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Rival *</label>
                        <input type="text" name="rival" class="form-control"
                               placeholder="Nombre del equipo rival"
                               value="<?= sanitize($_POST['rival'] ?? '') ?>" required>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Fecha *</label>
                            <input type="date" name="fecha" class="form-control"
                                   value="<?= sanitize($_POST['fecha'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hora</label>
                            <input type="time" name="hora" class="form-control"
                                   value="<?= sanitize($_POST['hora'] ?? '20:00') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado inicial</label>
                        <select name="estado" class="form-control">
                            <option value="abierto">Abierto (acepta apuestas)</option>
                            <option value="cerrado">Cerrado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea name="descripcion" class="form-control" rows="3"
                                  placeholder="Ej: Final de Champions FC26"><?= sanitize($_POST['descripcion'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Mercados -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-list"></i> Mercados Apostables</span>
                    <button type="button" onclick="addEvento()" class="btn btn-outline btn-sm">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body" style="padding:12px;">
                    <div id="eventosContainer">
                        <?php foreach ($predefinidos as $i => $pre): ?>
                        <div class="evento-row" id="evento_<?= $i ?>"
                             style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:8px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <span style="font-size:.78rem;color:var(--text-muted);">Mercado <?= $i+1 ?></span>
                                <button type="button" onclick="removeEvento(<?= $i ?>)"
                                        class="btn btn-danger btn-sm" style="padding:3px 8px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <input type="text" name="ev_desc[]" class="form-control"
                                   placeholder="Descripción" value="<?= sanitize($pre[0]) ?>"
                                   style="margin-bottom:6px;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <input type="number" name="ev_cuota[]" class="form-control"
                                       placeholder="Cuota" value="<?= $pre[1] ?>"
                                       step="0.01" min="1.01">
                                <select name="ev_jugador[]" class="form-control">
                                    <option value="ninguno" <?= $pre[2]==='ninguno'?'selected':'' ?>>Sin jugador</option>
                                    <?php foreach ($jugadores as $j): ?>
                                    <option value="<?= sanitize($j) ?>" <?= $pre[2]===$j?'selected':'' ?>>
                                        <?= sanitize($j) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px;display:flex;gap:12px;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Crear Partido
            </button>
            <a href="<?= SITE_URL ?>/admin/partidos.php" class="btn btn-outline btn-lg">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </form>
</div>
</div>

<script>
let eventoCount = <?= count($predefinidos) ?>;

// Opciones de jugadores generadas dinámicamente desde PHP
const jugadoresOptions = `
    <option value="ninguno">Sin jugador</option>
    <?php foreach ($jugadores as $j): ?>
    <option value="<?= sanitize($j) ?>"><?= sanitize($j) ?></option>
    <?php endforeach; ?>
`;

function addEvento() {
    const container = document.getElementById('eventosContainer');
    const div = document.createElement('div');
    div.className = 'evento-row';
    div.id = 'evento_' + eventoCount;
    div.style = 'border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:8px;';
    div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-size:.78rem;color:var(--text-muted);">Mercado ${eventoCount + 1}</span>
            <button type="button" onclick="removeEvento(${eventoCount})"
                    class="btn btn-danger btn-sm" style="padding:3px 8px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <input type="text" name="ev_desc[]" class="form-control"
               placeholder="Descripción del mercado" style="margin-bottom:6px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <input type="number" name="ev_cuota[]" class="form-control"
                   placeholder="Cuota (ej: 2.50)" step="0.01" min="1.01">
            <select name="ev_jugador[]" class="form-control">
                ${jugadoresOptions}
            </select>
        </div>`;
    container.appendChild(div);
    eventoCount++;
}

function removeEvento(id) {
    document.getElementById('evento_' + id)?.remove();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>