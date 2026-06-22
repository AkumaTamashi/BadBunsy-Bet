<?php
// ============================================================
// realizar.php — Endpoint AJAX para registrar apuestas
// ============================================================

// 1. Sesión ANTES de cualquier output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Buffer de salida para capturar warnings/notices PHP
ob_start();

// 3. Cabecera JSON siempre
header('Content-Type: application/json; charset=utf-8');

// 4. Permitir cookies de sesión en requests AJAX (crítico en algunos hostings)
header('Access-Control-Allow-Credentials: true');

// 5. Cargar dependencias
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// =====================================================
// Función helper para responder y salir limpiamente
// =====================================================
function jsonOut(array $data): void {
    ob_end_clean(); // descartar cualquier output anterior (warnings PHP, etc.)
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// =====================================================
// AUTENTICACIÓN — responde JSON, no redirige
// =====================================================
if (!isLoggedIn()) {
    jsonOut(['success' => false, 'message' => 'Sesión expirada. Recarga la página e inicia sesión de nuevo.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Método no permitido.']);
}

$db   = getDB();
$user = getCurrentUser();

if (!$user) {
    jsonOut(['success' => false, 'message' => 'No se encontró tu usuario. Inicia sesión de nuevo.']);
}

// =====================================================
// LEER Y SANEAR DATOS POST
// =====================================================
$monto      = floatval($_POST['monto']      ?? 0);
$tipo       = in_array($_POST['tipo'] ?? '', ['simple', 'combinada']) ? $_POST['tipo'] : 'simple';
$partido_id = intval($_POST['partido_id']   ?? 0);
$eventosRaw = $_POST['eventos']             ?? '[]';
$eventos    = json_decode($eventosRaw, true);

// =====================================================
// VALIDACIONES BÁSICAS
// =====================================================
if ($monto < 1000) {
    jsonOut(['success' => false, 'message' => 'El monto mínimo es 1.000 BB.']);
}

if (!is_array($eventos) || empty($eventos)) {
    jsonOut(['success' => false, 'message' => 'Selecciona al menos un evento.']);
}

if ($partido_id <= 0) {
    jsonOut(['success' => false, 'message' => 'Partido inválido.']);
}

// =====================================================
// VERIFICAR SALDO (leer de BD, no de sesión)
// =====================================================
$stmtSaldo = $db->prepare("SELECT saldo FROM usuarios WHERE id = ?");
$stmtSaldo->execute([$user['id']]);
$saldoActual = floatval($stmtSaldo->fetchColumn());

if ($monto > $saldoActual) {
    jsonOut(['success' => false, 'message' => 'Saldo insuficiente. Tienes ' . number_format($saldoActual, 0, ',', '.') . ' BB.']);
}

// =====================================================
// VERIFICAR PARTIDO ABIERTO
// =====================================================
$stmtPartido = $db->prepare("SELECT * FROM partidos WHERE id = ? AND estado = 'abierto'");
$stmtPartido->execute([$partido_id]);
$partido = $stmtPartido->fetch();

if (!$partido) {
    jsonOut(['success' => false, 'message' => 'Las apuestas para este partido están cerradas.']);
}

// =====================================================
// VALIDAR EVENTOS Y CALCULAR CUOTA TOTAL
// =====================================================
$cuotaTotal       = 1.0;
$eventosValidados = [];

foreach ($eventos as $eid) {
    $eid = intval($eid);
    if ($eid <= 0) continue;

    $stmtEv = $db->prepare("SELECT * FROM eventos WHERE id = ? AND partido_id = ? AND resultado = 'pendiente'");
    $stmtEv->execute([$eid, $partido_id]);
    $evento = $stmtEv->fetch();

    if (!$evento) {
        jsonOut(['success' => false, 'message' => 'El evento #' . $eid . ' no está disponible.']);
    }

    // Restricción: no apostar en tu propio jugador
    if (jugadorRelacionadoConEvento($user['jugador_asociado'], $evento['jugador_relacionado'])) {
        jsonOut(['success' => false, 'message' => 'No puedes apostar en eventos de ' . $user['jugador_asociado'] . ' (es tu jugador asociado).']);
    }

    $cuotaTotal *= floatval($evento['cuota']);
    $eventosValidados[] = $eid;
}

if (empty($eventosValidados)) {
    jsonOut(['success' => false, 'message' => 'No quedaron eventos válidos.']);
}

$cuotaTotal = round($cuotaTotal, 4);
$ganancia   = intval(floor($monto * $cuotaTotal));

// =====================================================
// GUARDAR EN BASE DE DATOS — transacción
// =====================================================
try {
    $db->beginTransaction();

    // 1. Insertar apuesta principal
    $stmtAp = $db->prepare("
        INSERT INTO apuestas (usuario_id, partido_id, monto, cuota_total, posible_ganancia, estado, tipo)
        VALUES (?, ?, ?, ?, ?, 'pendiente', ?)
    ");
    $stmtAp->execute([$user['id'], $partido_id, $monto, $cuotaTotal, $ganancia, $tipo]);
    $apuestaId = $db->lastInsertId();

    // 2. Insertar detalle de eventos
    $stmtDet = $db->prepare("INSERT INTO apuesta_detalle (apuesta_id, evento_id) VALUES (?, ?)");
    foreach ($eventosValidados as $eid) {
        $stmtDet->execute([$apuestaId, $eid]);
    }

    // 3. Descontar saldo
    $stmtUp = $db->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?");
    $stmtUp->execute([$monto, $user['id']]);

    // 4. Registrar movimiento
    $stmtMov = $db->prepare("
        INSERT INTO movimientos (usuario_id, tipo, monto, descripcion)
        VALUES (?, 'apuesta', ?, ?)
    ");
    $stmtMov->execute([
        $user['id'],
        $monto,
        'Apuesta #' . $apuestaId . ' — Bad Bunsy vs ' . $partido['rival']
    ]);

    $db->commit();

    jsonOut([
        'success'     => true,
        'message'     => '¡Apuesta registrada! Ganancia potencial: ' . number_format($ganancia, 0, ',', '.') . ' BB',
        'apuesta_id'  => $apuestaId,
        'nuevo_saldo' => $saldoActual - $monto
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonOut([
        'success' => false,
        'message' => 'Error interno al guardar la apuesta. Intenta de nuevo.',
        'debug'   => $e->getMessage()   // puedes quitar esto en producción
    ]);
}