<?php
// ============================================================
// Configuración de Base de Datos - Bad Bunsy Bet
// Compatible con InfinityFree
// ============================================================

// MOSTRAR ERRORES (QUITAR EN PRODUCCIÓN)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
// CONFIGURACIÓN MYSQL
// ============================================================

define('DB_HOST', 'sql105.infinityfree.com');

define('DB_USER', 'if0_42118587');

define('DB_PASS', 'huS9544Lez0JB');

define('DB_NAME', 'if0_42118587_db_betbunsy');

// InfinityFree a veces falla con utf8mb4
define('DB_CHARSET', 'utf8');

// ============================================================
// CONFIG GENERAL
// ============================================================

define('SITE_NAME', 'Bad Bunsy Bet');

define('SITE_URL', 'https://betbunsy.gamer.gd');

define('SALDO_INICIAL', 50000);

// ============================================================
// JUGADORES OFICIALES
// ============================================================

define('JUGADORES', [
    'Alex',
    'Rizzo',
    'Andrew',
    'Yamato',
    'Emanuel'
]);

// ============================================================
// INICIAR SESIÓN
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CONEXIÓN PDO
// ============================================================

function getDB() {

    static $pdo = null;

    if ($pdo === null) {

        try {

            $dsn = "mysql:host=" . DB_HOST .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHARSET;

            $options = [

                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                PDO::ATTR_EMULATE_PREPARES => false

            ];

            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

        } catch (PDOException $e) {

            // Mostrar error REAL para debug
            die("Error de conexión: " . $e->getMessage());

        }
    }

    return $pdo;
}
?>
