<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db    = getDB();
$error = '';
$success = '';

// ============================================================
// TABLA jugadores — crearla si no existe
// ============================================================
$db->exec("
    CREATE TABLE IF NOT EXISTS jugadores (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        nombre    VARCHAR(100) NOT NULL UNIQUE,
        posicion  VARCHAR(60)  NOT NULL DEFAULT '',
        activo    TINYINT(1)   NOT NULL DEFAULT 1,
        orden     INT          NOT NULL DEFAULT 0,
        creado_en DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Migrar jugadores hardcodeados si la tabla está vacía
$count = $db->query("SELECT COUNT(*) FROM jugadores")->fetchColumn();
if ($count == 0) {
    $defaults_jug = [
        ['Alex',    'DC',         1],
        ['Rizzo',   'MCO',        2],
        ['Andrew',  'ED',         3],
        ['Yamato',  'MCE',        4],
        ['Emanuel', 'ED Derecho', 5],
    ];
    $ins = $db->prepare("INSERT IGNORE INTO jugadores (nombre, posicion, orden) VALUES (?,?,?)");
    foreach ($defaults_jug as $j) $ins->execute($j);
}

// TABLA configuracion
$db->exec("
    CREATE TABLE IF NOT EXISTS configuracion (
        clave       VARCHAR(100) PRIMARY KEY,
        valor       TEXT         NOT NULL,
        tipo        VARCHAR(30)  NOT NULL DEFAULT 'texto',
        grupo       VARCHAR(60)  NOT NULL DEFAULT 'general',
        etiqueta    VARCHAR(120) NOT NULL DEFAULT '',
        descripcion VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$defaults_config = [
    ['site_name',         'Bad Bunsy Bet',                              'texto',   'sitio',    'Nombre del sitio',              'Aparece en el navbar y título del navegador.'],
    ['site_url',          'http://localhost/badbunsybet',               'texto',   'sitio',    'URL base (SITE_URL)',            'Sin barra final. Ej: https://midominio.com/badbunsybet'],
    ['site_descripcion',  'Plataforma privada de apuestas del club FC26','texto',  'sitio',    'Descripción corta',             'Se muestra en el footer.'],
    ['mantenimiento',     '0',    'bool',    'sitio',    'Modo mantenimiento',            'Bloquea el acceso a usuarios (admin sigue entrando).'],
    ['registro_abierto',  '1',    'bool',    'sitio',    'Registro abierto',              'Permite que nuevos usuarios se registren.'],
    ['saldo_inicial',     '50000','numero',  'apuestas', 'Saldo de bienvenida (BB)',      'Saldo que recibe cada usuario al registrarse.'],
    ['monto_minimo',      '1000', 'numero',  'apuestas', 'Monto mínimo por apuesta',      'En moneda virtual BB.'],
    ['monto_maximo',      '100000','numero', 'apuestas', 'Monto máximo por apuesta',      '0 = sin límite.'],
    ['max_eventos_combo', '10',   'numero',  'apuestas', 'Máx. eventos en combinada',     'Máximo de eventos que se pueden combinar.'],
    ['cuota_minima',      '1.10', 'decimal', 'apuestas', 'Cuota mínima permitida',        'No se pueden crear eventos con cuota menor.'],
    ['cuota_maxima',      '50.00','decimal', 'apuestas', 'Cuota máxima permitida',        '0 = sin límite.'],
    ['apuestas_activas',  '1',    'bool',    'apuestas', 'Apuestas habilitadas',          'Desactiva para bloquear TODAS las apuestas.'],
    ['color_primario',    '#22c55e','color', 'visual',   'Color verde principal',         'Botones, highlights, saldo.'],
    ['color_dorado',      '#facc15','color', 'visual',   'Color dorado / cuotas',         'Cuotas y elementos destacados.'],
    ['color_fondo',       '#0f172a','color', 'visual',   'Color de fondo',                'Fondo oscuro del sitio.'],
    ['color_tarjeta',     '#1e2d3d','color', 'visual',   'Color de tarjetas',             'Cards y paneles.'],
    ['color_rojo',        '#ef4444','color', 'visual',   'Color rojo / peligro',          'Apuestas perdidas, errores.'],
    ['intentos_login',    '5',    'numero',  'seguridad','Intentos máx. de login',        'Intentos fallidos antes de bloquear (0 = sin límite).'],
    ['sesion_duracion',   '120',  'numero',  'seguridad','Duración de sesión (min)',       'Minutos de inactividad antes de cerrar sesión.'],
    ['log_apuestas',      '1',    'bool',    'seguridad','Log de apuestas',               'Registrar todas las apuestas en movimientos.'],
    ['mostrar_debug',     '0',    'bool',    'bd',       'Mostrar errores PHP',            'Solo en desarrollo. NUNCA en producción.'],
    ['backup_auto',       '0',    'bool',    'bd',       'Backup automático',              'Función informativa. Requiere cron job.'],
];
$stmtIns = $db->prepare("INSERT IGNORE INTO configuracion (clave,valor,tipo,grupo,etiqueta,descripcion) VALUES (?,?,?,?,?,?)");
foreach ($defaults_config as $d) $stmtIns->execute($d);

// ============================================================
// PROCESAR ACCIONES POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = sanitize($_POST['accion'] ?? '');

    // ---- Guardar configuración general ----
    if ($accion === 'guardar_config') {
        $grupo   = sanitize($_POST['grupo'] ?? '');
        $claves  = $_POST['config'] ?? [];
        $stmtUpd = $db->prepare("UPDATE configuracion SET valor=? WHERE clave=?");
        foreach ($claves as $clave => $valor) {
            $clave = sanitize($clave);
            $tipo  = $db->prepare("SELECT tipo FROM configuracion WHERE clave=?");
            $tipo->execute([$clave]);
            $tipo = $tipo->fetchColumn();
            if ($tipo === 'bool')    $valor = '1';
            elseif ($tipo === 'color')   $valor = preg_match('/^#[0-9a-fA-F]{6}$/', $valor) ? $valor : '#ffffff';
            elseif ($tipo === 'numero')  $valor = strval(intval($valor));
            elseif ($tipo === 'decimal') $valor = strval(round(floatval($valor), 2));
            else $valor = trim($valor);
            $stmtUpd->execute([$valor, $clave]);
        }
        // Checkboxes desmarcados
        $bools = $db->query("SELECT clave FROM configuracion WHERE tipo='bool' AND grupo=".$db->quote($grupo))->fetchAll();
        foreach ($bools as $b) {
            if (!isset($_POST['config'][$b['clave']])) $stmtUpd->execute(['0', $b['clave']]);
        }
        $success = "✓ Configuración de «".ucfirst($grupo)."» guardada.";
    }

    // ---- Agregar jugador ----
    elseif ($accion === 'agregar_jugador') {
        $nombre   = trim(sanitize($_POST['jug_nombre']   ?? ''));
        $posicion = trim(sanitize($_POST['jug_posicion'] ?? ''));
        if (empty($nombre)) {
            $error = 'El nombre del jugador es obligatorio.';
        } elseif (strlen($nombre) > 60) {
            $error = 'Nombre demasiado largo (máx. 60 caracteres).';
        } else {
            $check = $db->prepare("SELECT id FROM jugadores WHERE nombre=?");
            $check->execute([$nombre]);
            if ($check->fetch()) {
                $error = "Ya existe un jugador llamado «$nombre».";
            } else {
                $orden = intval($db->query("SELECT COALESCE(MAX(orden),0)+1 FROM jugadores")->fetchColumn());
                $db->prepare("INSERT INTO jugadores (nombre, posicion, orden, activo) VALUES (?,?,?,1)")
                   ->execute([$nombre, $posicion, $orden]);
                $success = "✓ Jugador «$nombre» agregado correctamente.";
            }
        }
    }

    // ---- Editar jugador ----
    elseif ($accion === 'editar_jugador') {
        $jid      = intval($_POST['jug_id']       ?? 0);
        $nombre   = trim(sanitize($_POST['jug_nombre']   ?? ''));
        $posicion = trim(sanitize($_POST['jug_posicion'] ?? ''));
        $activo   = intval($_POST['jug_activo']   ?? 0);
        if ($jid <= 0 || empty($nombre)) {
            $error = 'Datos inválidos.';
        } else {
            // Verificar nombre duplicado en otro jugador
            $dup = $db->prepare("SELECT id FROM jugadores WHERE nombre=? AND id!=?");
            $dup->execute([$nombre, $jid]);
            if ($dup->fetch()) {
                $error = "Ya existe otro jugador con el nombre «$nombre».";
            } else {
                // Actualizar nombre en usuarios que lo usen (si cambió)
                $old = $db->prepare("SELECT nombre FROM jugadores WHERE id=?");
                $old->execute([$jid]);
                $oldNombre = $old->fetchColumn();
                if ($oldNombre && $oldNombre !== $nombre) {
                    $db->prepare("UPDATE usuarios SET jugador_asociado=? WHERE jugador_asociado=?")
                       ->execute([$nombre, $oldNombre]);
                    $db->prepare("UPDATE eventos SET jugador_relacionado=? WHERE jugador_relacionado=?")
                       ->execute([$nombre, $oldNombre]);
                }
                $db->prepare("UPDATE jugadores SET nombre=?, posicion=?, activo=? WHERE id=?")
                   ->execute([$nombre, $posicion, $activo ? 1 : 0, $jid]);
                $success = "✓ Jugador actualizado.";
            }
        }
    }

    // ---- Eliminar jugador ----
    elseif ($accion === 'eliminar_jugador') {
        $jid = intval($_POST['jug_id'] ?? 0);
        if ($jid > 0) {
            $jug = $db->prepare("SELECT nombre FROM jugadores WHERE id=?");
            $jug->execute([$jid]);
            $jnombre = $jug->fetchColumn();
            // Verificar que ningún usuario lo use
            $enUso = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE jugador_asociado=?");
            $enUso->execute([$jnombre]);
            if ($enUso->fetchColumn() > 0) {
                $error = "No se puede eliminar: hay usuarios asociados a «$jnombre». Desactívalo en su lugar.";
            } else {
                $db->prepare("DELETE FROM jugadores WHERE id=?")->execute([$jid]);
                $success = "✓ Jugador «$jnombre» eliminado.";
            }
        }
    }

    // ---- Reordenar (drag & drop) ----
    elseif ($accion === 'reordenar') {
        $orden = json_decode($_POST['orden'] ?? '[]', true);
        if (is_array($orden)) {
            $stmtOrd = $db->prepare("UPDATE jugadores SET orden=? WHERE id=?");
            foreach ($orden as $pos => $id) {
                $stmtOrd->execute([$pos + 1, intval($id)]);
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
    }

    // ---- Acciones BD ----
    elseif ($accion === 'reset_saldos') {
        $nuevoSaldo = intval($_POST['nuevo_saldo'] ?? 0);
        if ($nuevoSaldo >= 0) {
            $db->prepare("UPDATE usuarios SET saldo=? WHERE rol='usuario'")->execute([$nuevoSaldo]);
            $users = $db->query("SELECT id FROM usuarios WHERE rol='usuario'")->fetchAll();
            $mov = $db->prepare("INSERT INTO movimientos (usuario_id,tipo,monto,descripcion) VALUES (?,'ajuste',?,'Reset masivo de saldo por admin')");
            foreach ($users as $u) $mov->execute([$u['id'], $nuevoSaldo]);
            $success = "✓ Saldo reseteado a ".number_format($nuevoSaldo,0,',','.')." BB para ".count($users)." usuarios.";
        } else { $error = 'Monto inválido.'; }
    }
    elseif ($accion === 'dar_saldo_todos') {
        $bonus = intval($_POST['bonus_saldo'] ?? 0);
        if ($bonus > 0) {
            $db->prepare("UPDATE usuarios SET saldo=saldo+? WHERE rol='usuario'")->execute([$bonus]);
            $users = $db->query("SELECT id FROM usuarios WHERE rol='usuario'")->fetchAll();
            $mov = $db->prepare("INSERT INTO movimientos (usuario_id,tipo,monto,descripcion) VALUES (?,'deposito',?,'Bonus masivo entregado por admin')");
            foreach ($users as $u) $mov->execute([$u['id'], $bonus]);
            $success = "✓ Se dieron ".number_format($bonus,0,',','.')." BB a todos los usuarios.";
        } else { $error = 'Monto inválido.'; }
    }
    elseif ($accion === 'cancelar_apuestas_partido') {
        $pid = intval($_POST['partido_cancelar'] ?? 0);
        if ($pid > 0) {
            $aps = $db->prepare("SELECT * FROM apuestas WHERE partido_id=? AND estado='pendiente'");
            $aps->execute([$pid]); $aps = $aps->fetchAll();
            $c1 = $db->prepare("UPDATE apuestas SET estado='cancelada' WHERE id=?");
            $c2 = $db->prepare("UPDATE usuarios SET saldo=saldo+? WHERE id=?");
            $c3 = $db->prepare("INSERT INTO movimientos (usuario_id,tipo,monto,descripcion) VALUES (?,'deposito',?,'Devolución: apuesta cancelada por admin')");
            foreach ($aps as $ap) { $c1->execute([$ap['id']]); $c2->execute([$ap['monto'],$ap['usuario_id']]); $c3->execute([$ap['usuario_id'],$ap['monto']]); }
            $success = "✓ ".count($aps)." apuesta(s) canceladas y saldos devueltos.";
        } else { $error = 'Selecciona un partido.'; }
    }
    elseif ($accion === 'limpiar_movimientos') {
        $dias = intval($_POST['dias_movimientos'] ?? 90);
        $db->prepare("DELETE FROM movimientos WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$dias]);
        $success = "✓ Movimientos de más de $dias días eliminados.";
    }
}

// ============================================================
// CARGAR DATOS
// ============================================================
$jugadores_lista = $db->query("SELECT * FROM jugadores ORDER BY orden ASC, id ASC")->fetchAll();

$config = [];
foreach ($db->query("SELECT * FROM configuracion ORDER BY grupo,clave")->fetchAll() as $r) {
    $config[$r['grupo']][$r['clave']] = $r;
}

$partidos    = $db->query("SELECT id,rival,fecha FROM partidos WHERE estado!='finalizado' ORDER BY fecha DESC")->fetchAll();
$stats       = $db->query("SELECT
    (SELECT COUNT(*) FROM usuarios WHERE rol='usuario') as usuarios,
    (SELECT COUNT(*) FROM apuestas WHERE estado='pendiente') as pendientes,
    (SELECT COUNT(*) FROM partidos WHERE estado='abierto') as abiertos,
    (SELECT SUM(saldo) FROM usuarios WHERE rol='usuario') as saldo_total
")->fetch();

$grupos_info = [
    'sitio'     => ['icon'=>'fa-globe',      'label'=>'Sitio',           'color'=>'#3b82f6'],
    'apuestas'  => ['icon'=>'fa-ticket-alt', 'label'=>'Apuestas',        'color'=>'#22c55e'],
    'visual'    => ['icon'=>'fa-palette',    'label'=>'Visual / Colores','color'=>'#a855f7'],
    'seguridad' => ['icon'=>'fa-shield-alt', 'label'=>'Seguridad',       'color'=>'#ef4444'],
    'bd'        => ['icon'=>'fa-database',   'label'=>'BD / Sistema',    'color'=>'#f97316'],
];

$pageTitle = 'Configuración';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ---- Tabs ---- */
.config-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;}
.config-tab{display:flex;align-items:center;gap:8px;padding:9px 18px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-secondary);cursor:pointer;font-size:.85rem;font-weight:600;transition:all .2s;text-decoration:none;}
.config-tab:hover{border-color:var(--green);color:var(--text-primary);}
.config-tab.active{background:var(--green-glow);border-color:var(--green);color:var(--green);}
.config-panel{display:none;} .config-panel.active{display:block;}
/* ---- Config rows ---- */
.config-row{display:flex;justify-content:space-between;align-items:flex-start;padding:16px 0;border-bottom:1px solid var(--border);gap:20px;flex-wrap:wrap;}
.config-row:last-child{border-bottom:none;}
.config-label{flex:1;min-width:200px;}
.config-label strong{font-size:.9rem;display:block;color:var(--text-primary);}
.config-label span{font-size:.77rem;color:var(--text-muted);margin-top:3px;display:block;}
.config-input{flex-shrink:0;min-width:220px;}
/* ---- Toggle ---- */
.toggle-switch{position:relative;width:46px;height:26px;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;inset:0;background:var(--bg-hover);border-radius:26px;cursor:pointer;transition:.3s;border:1px solid var(--border);}
.toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:var(--text-muted);border-radius:50%;transition:.3s;}
.toggle-switch input:checked+.toggle-slider{background:var(--green-glow);border-color:var(--green);}
.toggle-switch input:checked+.toggle-slider:before{background:var(--green);transform:translateX(20px);}
/* ---- Color ---- */
.color-preview{width:36px;height:36px;border-radius:6px;border:2px solid var(--border);display:inline-block;vertical-align:middle;margin-right:8px;}
/* ---- Action cards ---- */
.action-card{background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:18px 20px;margin-bottom:14px;}
.action-card h4{font-size:.9rem;font-weight:700;margin-bottom:6px;display:flex;align-items:center;gap:8px;}
.action-card p{font-size:.78rem;color:var(--text-muted);margin-bottom:12px;line-height:1.5;}
/* ---- Stats mini ---- */
.stat-mini{background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:12px 16px;text-align:center;}
.stat-mini .val{font-size:1.4rem;font-weight:800;}
.stat-mini .lbl{font-size:.72rem;color:var(--text-muted);margin-top:2px;}
/* ---- Jugadores ---- */
.jug-card{background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:0;margin-bottom:8px;transition:all .2s;overflow:hidden;}
.jug-card:hover{border-color:var(--green);}
.jug-card-head{display:flex;align-items:center;gap:14px;padding:12px 16px;cursor:grab;}
.jug-card.editing{border-color:var(--gold);}
.jug-card-edit{padding:14px 16px;border-top:1px solid var(--border);background:var(--bg-secondary);display:none;}
.jug-card.editing .jug-card-edit{display:block;}
.drag-handle{color:var(--text-muted);cursor:grab;font-size:1rem;flex-shrink:0;}
.drag-handle:active{cursor:grabbing;}
.jug-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--green),#3b82f6);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;flex-shrink:0;}
.jug-inactive .jug-avatar{background:var(--text-muted);opacity:.5;}
.jug-nombre{font-weight:700;font-size:.9rem;}
.jug-pos{font-size:.75rem;color:var(--text-muted);}
.jug-sortable{list-style:none;padding:0;margin:0;}
.jug-sortable li{display:block;}
.sortable-ghost{opacity:.3;border:2px dashed var(--green)!important;}
.badge-activo{background:var(--green-glow);color:var(--green);border:1px solid var(--green);}
.badge-inactivo{background:rgba(100,116,139,.15);color:var(--text-muted);border:1px solid var(--border);}
</style>

<div class="admin-layout">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">

    <!-- Header -->
    <div class="page-header d-flex align-center gap-2" style="justify-content:space-between;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h1 class="page-title"><i class="fas fa-sliders-h"></i> Configuración</h1>
            <p class="page-subtitle">Control total de la plataforma Bad Bunsy Bet</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
            <div class="stat-mini"><div class="val text-green"><?= $stats['usuarios'] ?></div><div class="lbl">Usuarios</div></div>
            <div class="stat-mini"><div class="val text-gold"><?= $stats['pendientes'] ?></div><div class="lbl">Apuestas pend.</div></div>
            <div class="stat-mini"><div class="val" style="color:var(--blue);font-size:1rem;"><?= number_format($stats['saldo_total']??0,0,',','.') ?></div><div class="lbl">BB circulación</div></div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="flash-message flash-error" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-exclamation-circle"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flash-message flash-success" style="position:relative;top:auto;right:auto;margin-bottom:16px;animation:none;">
        <i class="fas fa-check-circle"></i> <?= $success ?>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="config-tabs">
        <?php foreach ($grupos_info as $gKey => $gInfo): ?>
        <a class="config-tab" onclick="switchTab('<?= $gKey ?>')" href="#<?= $gKey ?>">
            <i class="fas <?= $gInfo['icon'] ?>" style="color:<?= $gInfo['color'] ?>"></i> <?= $gInfo['label'] ?>
        </a>
        <?php endforeach; ?>
        <a class="config-tab" onclick="switchTab('jugadores')" href="#jugadores">
            <i class="fas fa-users" style="color:#facc15"></i> Jugadores
        </a>
        <a class="config-tab" onclick="switchTab('acciones')" href="#acciones">
            <i class="fas fa-bolt" style="color:#f59e0b"></i> Acciones BD
        </a>
    </div>

    <!-- ================================================================
         PANELES DE CONFIGURACIÓN GENERAL
    ================================================================ -->
    <?php foreach ($grupos_info as $gKey => $gInfo): ?>
    <div class="config-panel" id="panel_<?= $gKey ?>">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="fas <?= $gInfo['icon'] ?>" style="color:<?= $gInfo['color'] ?>"></i>
                    Configuración de <?= $gInfo['label'] ?>
                </span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_config">
                    <input type="hidden" name="grupo"  value="<?= $gKey ?>">
                    <?php foreach ($config[$gKey] ?? [] as $clave => $row): ?>
                    <div class="config-row">
                        <div class="config-label">
                            <strong><?= sanitize($row['etiqueta']) ?></strong>
                            <span><?= sanitize($row['descripcion']) ?></span>
                            <code style="font-size:.7rem;background:var(--bg-hover);padding:1px 5px;border-radius:4px;margin-top:4px;display:inline-block;"><?= $clave ?></code>
                        </div>
                        <div class="config-input">
                        <?php if ($row['tipo']==='bool'): ?>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="config[<?= $clave ?>]" value="1" <?= $row['valor']==='1'?'checked':'' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span style="font-size:.82rem;color:var(--text-secondary);" id="lbl_<?= $clave ?>"><?= $row['valor']==='1'?'Activado':'Desactivado' ?></span>
                            </label>
                        <?php elseif ($row['tipo']==='color'): ?>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="color-preview" id="prev_<?= $clave ?>" style="background:<?= sanitize($row['valor']) ?>"></span>
                                <input type="color" name="config[<?= $clave ?>]" value="<?= sanitize($row['valor']) ?>"
                                       class="form-control" style="width:100px;height:40px;padding:2px 4px;cursor:pointer;"
                                       oninput="document.getElementById('prev_<?= $clave ?>').style.background=this.value;document.getElementById('hex_<?= $clave ?>').value=this.value;">
                                <input type="text" id="hex_<?= $clave ?>" value="<?= sanitize($row['valor']) ?>"
                                       class="form-control" style="width:100px;font-family:monospace;font-size:.85rem;"
                                       oninput="syncColor('<?= $clave ?>',this.value)">
                            </div>
                        <?php elseif ($row['tipo']==='numero'): ?>
                            <input type="number" name="config[<?= $clave ?>]" value="<?= sanitize($row['valor']) ?>"
                                   class="form-control" style="width:160px;" min="0" step="1">
                        <?php elseif ($row['tipo']==='decimal'): ?>
                            <input type="number" name="config[<?= $clave ?>]" value="<?= sanitize($row['valor']) ?>"
                                   class="form-control" style="width:160px;" min="1.01" step="0.01">
                        <?php else: ?>
                            <input type="text" name="config[<?= $clave ?>]" value="<?= sanitize($row['valor']) ?>"
                                   class="form-control" style="min-width:280px;">
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Guardar <?= $gInfo['label'] ?>
                        </button>
                        <?php if ($gKey==='visual'): ?>
                        <button type="button" onclick="previewColores()" class="btn btn-outline btn-lg">
                            <i class="fas fa-eye"></i> Preview en vivo
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ================================================================
         PANEL JUGADORES
    ================================================================ -->
    <div class="config-panel" id="panel_jugadores">
        <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

            <!-- Lista de jugadores (arrastrable) -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-users" style="color:var(--gold)"></i> Jugadores del club</span>
                    <span class="badge badge-blue"><?= count($jugadores_lista) ?> total</span>
                </div>
                <div class="card-body" style="padding:12px;">

                    <?php if (empty($jugadores_lista)): ?>
                    <div class="empty-state" style="padding:30px;"><p>No hay jugadores. Agrega uno desde el panel derecho.</p></div>
                    <?php else: ?>

                    <p style="font-size:.77rem;color:var(--text-muted);margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-grip-vertical"></i> Arrastra para cambiar el orden · Haz clic en un jugador para editarlo
                    </p>

                    <ul class="jug-sortable" id="jugadoresSortable">
                        <?php foreach ($jugadores_lista as $jug): ?>
                        <li data-id="<?= $jug['id'] ?>">
                            <div class="jug-card <?= $jug['activo']?'':'jug-inactive' ?>" id="jugCard_<?= $jug['id'] ?>">
                                <div class="jug-card-head" onclick="toggleEditJug(<?= $jug['id'] ?>)">
                                    <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                                    <div class="jug-avatar"><?= strtoupper(substr($jug['nombre'],0,2)) ?></div>
                                    <div style="flex:1;">
                                        <div class="jug-nombre"><?= sanitize($jug['nombre']) ?></div>
                                        <div class="jug-pos"><?= sanitize($jug['posicion']) ?: 'Sin posición' ?></div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span class="badge <?= $jug['activo']?'badge-activo':'badge-inactivo' ?>" style="font-size:.68rem;">
                                            <?= $jug['activo']?'Activo':'Inactivo' ?>
                                        </span>
                                        <?php
                                        // Contar usuarios asociados
                                        $nUsr = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE jugador_asociado=?");
                                        $nUsr->execute([$jug['nombre']]);
                                        $nUsr = $nUsr->fetchColumn();
                                        ?>
                                        <?php if ($nUsr > 0): ?>
                                        <span class="badge badge-blue" style="font-size:.68rem;" title="Usuarios asociados">
                                            <i class="fas fa-user"></i> <?= $nUsr ?>
                                        </span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-down" id="chevron_<?= $jug['id'] ?>" style="color:var(--text-muted);font-size:.75rem;transition:transform .2s;"></i>
                                    </div>
                                </div>

                                <!-- Formulario edición inline -->
                                <div class="jug-card-edit" id="editJug_<?= $jug['id'] ?>">
                                    <form method="POST">
                                        <input type="hidden" name="accion"    value="editar_jugador">
                                        <input type="hidden" name="jug_id"    value="<?= $jug['id'] ?>">
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                            <div class="form-group" style="margin:0;">
                                                <label class="form-label" style="font-size:.78rem;">Nombre</label>
                                                <input type="text" name="jug_nombre" class="form-control"
                                                       value="<?= sanitize($jug['nombre']) ?>" required maxlength="60">
                                            </div>
                                            <div class="form-group" style="margin:0;">
                                                <label class="form-label" style="font-size:.78rem;">Posición</label>
                                                <input type="text" name="jug_posicion" class="form-control"
                                                       value="<?= sanitize($jug['posicion']) ?>" placeholder="Ej: DC, MCO, ED…">
                                            </div>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.82rem;">
                                                <label class="toggle-switch">
                                                    <input type="checkbox" name="jug_activo" value="1" <?= $jug['activo']?'checked':'' ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span>Activo (aparece en registro y apuestas)</span>
                                            </label>
                                            <div style="display:flex;gap:8px;">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-save"></i> Guardar
                                                </button>
                                                <?php if ($nUsr == 0): ?>
                                                <button type="button" onclick="eliminarJugador(<?= $jug['id'] ?>, '<?= sanitize($jug['nombre']) ?>')"
                                                        class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-outline btn-sm" disabled
                                                        title="Tiene <?= $nUsr ?> usuario(s) asociado(s)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button type="button" onclick="toggleEditJug(<?= $jug['id'] ?>)" class="btn btn-outline btn-sm">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <!-- Forma para eliminar (submit separado) -->
                                    <form id="formEliminar_<?= $jug['id'] ?>" method="POST" style="display:none;">
                                        <input type="hidden" name="accion" value="eliminar_jugador">
                                        <input type="hidden" name="jug_id" value="<?= $jug['id'] ?>">
                                    </form>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php endif; ?>
                </div>
            </div>

            <!-- Panel derecho: agregar jugador + info -->
            <div>
                <!-- Agregar nuevo -->
                <div class="card" style="margin-bottom:16px;">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-plus-circle" style="color:var(--green)"></i> Agregar jugador</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="accion" value="agregar_jugador">
                            <div class="form-group">
                                <label class="form-label">Nombre del jugador <span style="color:var(--red)">*</span></label>
                                <input type="text" name="jug_nombre" class="form-control"
                                       placeholder="Ej: Carlos" maxlength="60" required autocomplete="off">
                                <p class="form-text">Debe ser único. Este nombre se usará en el registro y en los mercados de apuesta.</p>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Posición <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
                                <input type="text" name="jug_posicion" class="form-control"
                                       placeholder="Ej: DC, MCO, POR…" maxlength="60">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-user-plus"></i> Agregar jugador
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Info / ayuda -->
                <div class="card">
                    <div class="card-body" style="padding:16px 18px;">
                        <p style="font-size:.8rem;font-weight:700;color:var(--text-muted);margin-bottom:10px;">
                            <i class="fas fa-info-circle" style="color:var(--blue)"></i> ¿Cómo funciona?
                        </p>
                        <ul style="font-size:.78rem;color:var(--text-muted);padding-left:14px;line-height:2;">
                            <li>Los jugadores <strong>activos</strong> aparecen en el registro de nuevos usuarios y en los selectores de mercados de apuesta.</li>
                            <li>Los <strong>inactivos</strong> no aparecen en nuevos registros pero conservan sus datos históricos.</li>
                            <li>No puedes <strong>eliminar</strong> un jugador que tenga usuarios asociados — desactívalo.</li>
                            <li>Si cambias el <strong>nombre</strong>, se actualiza automáticamente en todos los usuarios y eventos existentes.</li>
                            <li>Arrastra las filas para cambiar el <strong>orden</strong> en que aparecen en los desplegables.</li>
                        </ul>

                        <div style="margin-top:14px;padding:12px;background:rgba(250,204,21,.08);border:1px solid rgba(250,204,21,.2);border-radius:8px;">
                            <p style="font-size:.78rem;color:var(--gold);font-weight:600;margin-bottom:6px;">
                                <i class="fas fa-database"></i> Migración de columna requerida
                            </p>
                            <p style="font-size:.75rem;color:var(--text-muted);line-height:1.6;">
                                Si instalaste la BD con el <code>database.sql</code> original, la columna
                                <code>jugador_asociado</code> es de tipo <code>ENUM</code> fijo.
                                Ejecuta este SQL una sola vez para hacerla dinámica:
                            </p>
                            <code style="display:block;background:var(--bg-primary);padding:8px 10px;border-radius:6px;font-size:.72rem;margin-top:8px;color:var(--green);line-height:1.8;">
                                ALTER TABLE usuarios<br>
                                MODIFY COLUMN jugador_asociado<br>
                                VARCHAR(100) NOT NULL;<br><br>
                                ALTER TABLE eventos<br>
                                MODIFY COLUMN jugador_relacionado<br>
                                VARCHAR(100) NOT NULL DEFAULT 'ninguno';
                            </code>
                            <button onclick="copiarSQL()" class="btn btn-outline btn-sm" style="margin-top:8px;width:100%;">
                                <i class="fas fa-copy"></i> Copiar SQL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         PANEL ACCIONES BD
    ================================================================ -->
    <div class="config-panel" id="panel_acciones">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div class="action-card">
                <h4><i class="fas fa-coins" style="color:var(--gold)"></i> Resetear saldo de todos</h4>
                <p>Fija el saldo de todos los usuarios a un valor específico.</p>
                <form method="POST" onsubmit="return confirm('¿Resetear saldo de TODOS los usuarios?')">
                    <input type="hidden" name="accion" value="reset_saldos">
                    <div style="display:flex;gap:10px;">
                        <input type="number" name="nuevo_saldo" class="form-control" placeholder="Nuevo saldo BB" min="0" step="1000" style="flex:1;">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-sync"></i> Reset</button>
                    </div>
                </form>
            </div>
            <div class="action-card">
                <h4><i class="fas fa-gift" style="color:var(--green)"></i> Dar bonus a todos</h4>
                <p>Suma BB a todos los usuarios. Se registra como depósito.</p>
                <form method="POST" onsubmit="return confirm('¿Dar bonus a todos los usuarios?')">
                    <input type="hidden" name="accion" value="dar_saldo_todos">
                    <div style="display:flex;gap:10px;">
                        <input type="number" name="bonus_saldo" class="form-control" placeholder="BB a dar" min="100" step="100" style="flex:1;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Dar</button>
                    </div>
                </form>
            </div>
            <div class="action-card">
                <h4><i class="fas fa-ban" style="color:var(--red)"></i> Cancelar apuestas de un partido</h4>
                <p>Cancela apuestas pendientes y devuelve el saldo.</p>
                <form method="POST" onsubmit="return confirm('¿Cancelar y devolver saldos?')">
                    <input type="hidden" name="accion" value="cancelar_apuestas_partido">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <select name="partido_cancelar" class="form-control" style="flex:1;" required>
                            <option value="">— Selecciona partido —</option>
                            <?php foreach ($partidos as $p): ?>
                            <option value="<?= $p['id'] ?>">vs <?= sanitize($p['rival']) ?> (<?= date('d/m/Y',strtotime($p['fecha'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times-circle"></i> Cancelar</button>
                    </div>
                </form>
            </div>
            <div class="action-card">
                <h4><i class="fas fa-broom" style="color:var(--text-muted)"></i> Limpiar historial</h4>
                <p>Elimina movimientos antiguos. <strong>Irreversible.</strong></p>
                <form method="POST" onsubmit="return confirm('¿Eliminar movimientos antiguos?')">
                    <input type="hidden" name="accion" value="limpiar_movimientos">
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="number" name="dias_movimientos" class="form-control" value="90" min="7" style="flex:1;">
                        <span style="font-size:.8rem;color:var(--text-muted);white-space:nowrap;">días</span>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stats BD -->
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-database"></i> Estado de la base de datos</span>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
                    <?php foreach (['usuarios','partidos','eventos','apuestas','apuesta_detalle','movimientos','jugadores','configuracion'] as $tabla): ?>
                    <?php try { $cnt=$db->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn(); } catch(Exception $e){$cnt='?';} ?>
                    <div class="stat-mini">
                        <div class="val" style="font-size:1.2rem;color:var(--green);"><?= $cnt ?></div>
                        <div class="lbl"><?= $tabla ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /admin-main -->
</div><!-- /admin-layout -->

<!-- Sortable.js para drag & drop -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
// ---- Tabs ----
function switchTab(id) {
    document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.config-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel_' + id)?.classList.add('active');
    document.querySelectorAll('.config-tab').forEach(t => {
        if (t.getAttribute('href') === '#' + id) t.classList.add('active');
    });
    history.replaceState(null, '', '#' + id);
}
window.addEventListener('DOMContentLoaded', () => {
    const hash = location.hash.replace('#','') || 'sitio';
    switchTab(hash);

    // Toggle labels
    document.querySelectorAll('.toggle-switch input[type=checkbox]').forEach(cb => {
        cb.addEventListener('change', function() {
            const key = this.name.replace('config[','').replace(']','');
            const lbl = document.getElementById('lbl_' + key);
            if (lbl) lbl.textContent = this.checked ? 'Activado' : 'Desactivado';
        });
    });

    // Drag & drop jugadores
    const el = document.getElementById('jugadoresSortable');
    if (el) {
        Sortable.create(el, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const orden = [...el.querySelectorAll('li[data-id]')].map(li => li.dataset.id);
                fetch('', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: 'accion=reordenar&orden=' + encodeURIComponent(JSON.stringify(orden)),
                    credentials: 'same-origin'
                });
            }
        });
    }
});

// ---- Edición inline jugador ----
function toggleEditJug(id) {
    const card    = document.getElementById('jugCard_' + id);
    const chevron = document.getElementById('chevron_' + id);
    const editing = card.classList.contains('editing');
    // Cerrar todos
    document.querySelectorAll('.jug-card.editing').forEach(c => {
        c.classList.remove('editing');
    });
    document.querySelectorAll('[id^="chevron_"]').forEach(c => c.style.transform = '');
    if (!editing) {
        card.classList.add('editing');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
}

// ---- Eliminar jugador ----
function eliminarJugador(id, nombre) {
    if (!confirm('¿Eliminar al jugador «' + nombre + '»? Esta acción no se puede deshacer.')) return;
    document.getElementById('formEliminar_' + id)?.submit();
}

// ---- Color preview ----
function syncColor(clave, hex) {
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        const ci = document.querySelector(`input[name="config[${clave}]"][type=color]`);
        const pr = document.getElementById('prev_' + clave);
        if (ci) ci.value = hex;
        if (pr) pr.style.background = hex;
    }
}

function previewColores() {
    const map = {color_primario:'--green',color_dorado:'--gold',color_fondo:'--bg-primary',color_tarjeta:'--bg-card',color_rojo:'--red'};
    Object.entries(map).forEach(([c,v]) => {
        const i = document.querySelector(`input[name="config[${c}]"][type=color]`);
        if (i) document.documentElement.style.setProperty(v, i.value);
    });
    showAlert('info','Preview aplicado. Guarda para que sea permanente.');
}

// ---- Copiar SQL de migración ----
function copiarSQL() {
    const sql = `ALTER TABLE usuarios MODIFY COLUMN jugador_asociado VARCHAR(100) NOT NULL;\nALTER TABLE eventos MODIFY COLUMN jugador_relacionado VARCHAR(100) NOT NULL DEFAULT 'ninguno';`;
    navigator.clipboard?.writeText(sql).then(() => showAlert('success','SQL copiado al portapapeles.'));
}

// ---- Alert ----
function showAlert(type, message) {
    document.querySelector('.dynamic-alert')?.remove();
    const a = document.createElement('div');
    a.className = `flash-message flash-${type==='info'?'info':type} dynamic-alert`;
    a.innerHTML = `<i class="fas fa-info-circle"></i> ${message} <button onclick="this.parentElement.remove()">×</button>`;
    document.body.appendChild(a);
    setTimeout(() => { a.style.animation='slideIn .3s ease reverse'; setTimeout(()=>a.remove(),300); }, 5000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>