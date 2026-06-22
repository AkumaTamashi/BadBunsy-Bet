<?php
// ============================================================
// Funciones de Sesión y Autenticación
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function actualizarSaldo($usuario_id, $monto, $tipo, $descripcion) {
    $db = getDB();
    // Actualizar saldo
    if (in_array($tipo, ['ganancia', 'deposito'])) {
        $db->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?")->execute([$monto, $usuario_id]);
    } else {
        $db->prepare("UPDATE usuarios SET saldo = saldo - ? WHERE id = ?")->execute([$monto, $usuario_id]);
    }
    // Registrar movimiento
    $db->prepare("INSERT INTO movimientos (usuario_id, tipo, monto, descripcion) VALUES (?,?,?,?)")
       ->execute([$usuario_id, $tipo, $monto, $descripcion]);
}

function formatMoney($amount) {
    return number_format($amount, 0, ',', '.');
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function jugadorRelacionadoConEvento($jugador_asociado, $jugador_evento) {
    if ($jugador_evento === 'ninguno') return false;
    return strtolower($jugador_asociado) === strtolower($jugador_evento);
}

// ============================================================
// Jugadores dinámicos desde BD
// ============================================================

/**
 * Devuelve los jugadores activos desde la tabla `jugadores`.
 * Si la tabla no existe aún (instalación vieja), cae al array JUGADORES.
 * Resultado: array de strings con los nombres.
 */
function getJugadoresActivos(): array {
    try {
        $db   = getDB();
        $rows = $db->query(
            "SELECT nombre FROM jugadores WHERE activo = 1 ORDER BY orden ASC, id ASC"
        )->fetchAll();
        if (!empty($rows)) {
            return array_column($rows, 'nombre');
        }
    } catch (Exception $e) {
        // tabla no existe aún → fallback
    }
    // Fallback a la constante hardcodeada
    return defined('JUGADORES') ? JUGADORES : [];
}

/**
 * Devuelve jugadores activos como array de objetos con nombre + posicion.
 */
function getJugadoresCompletos(): array {
    try {
        $db = getDB();
        return $db->query(
            "SELECT nombre, posicion FROM jugadores WHERE activo = 1 ORDER BY orden ASC, id ASC"
        )->fetchAll();
    } catch (Exception $e) {
        // fallback
        $out = [];
        foreach (getJugadoresActivos() as $n) {
            $out[] = ['nombre' => $n, 'posicion' => ''];
        }
        return $out;
    }
}

/**
 * Verifica si un nombre de jugador es válido (activo en BD).
 */
function esJugadorValido(string $nombre): bool {
    return in_array($nombre, getJugadoresActivos(), true);
}