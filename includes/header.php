<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?>Bad Bunsy Bet</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <a href="<?= SITE_URL ?>/index.php" class="nav-brand">
            <span class="brand-icon">⚽</span>
            <span class="brand-text">Bad<span class="brand-accent">Bunsy</span>Bet</span>
        </a>
        
        <div class="nav-menu" id="navMenu">
            <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="<?= SITE_URL ?>/partidos/lista.php" class="nav-link">
                    <i class="fas fa-futbol"></i> Partidos
                </a>
                <a href="<?= SITE_URL ?>/apuestas/mis_apuestas.php" class="nav-link">
                    <i class="fas fa-ticket-alt"></i> Mis Apuestas
                </a>
                <a href="<?= SITE_URL ?>/usuarios/transferir.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'transferir.php' ? 'active' : '' ?>"><i class="fas fa-paper-plane"></i> Transferir</a>
                <a href="<?= SITE_URL ?>/usuarios/ranking.php" class="nav-link">
                    <i class="fas fa-trophy"></i> Ranking
                </a>
                <?php if (isAdmin()): ?>
                <a href="<?= SITE_URL ?>/admin/index.php" class="nav-link nav-admin">
                    <i class="fas fa-cog"></i> Admin
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="nav-actions">
            <?php if (isLoggedIn() && $currentUser): ?>
                <div class="nav-balance">
                    <i class="fas fa-coins"></i>
                    <span><?= formatMoney($currentUser['saldo']) ?> BB</span>
                </div>
                <div class="nav-user-menu">
                    <button class="nav-user-btn" onclick="toggleUserMenu()">
                        <div class="user-avatar"><?= strtoupper(substr($currentUser['nombre'], 0, 2)) ?></div>
                        <span><?= sanitize($currentUser['nombre']) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="<?= SITE_URL ?>/usuarios/perfil.php"><i class="fas fa-user"></i> Mi Perfil</a>
                        <a href="<?= SITE_URL ?>/usuarios/transferir.php"><i class="fas fa-paper-plane"></i> Transferir BB</a>
                        <a href="<?= SITE_URL ?>/usuarios/movimientos.php"><i class="fas fa-history"></i> Movimientos</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= SITE_URL ?>/logout.php" class="dropdown-danger"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline">Ingresar</a>
                <a href="<?= SITE_URL ?>/registro.php" class="btn btn-primary">Registrarse</a>
            <?php endif; ?>
            <button class="nav-toggle" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</nav>

<?php if ($flash): ?>
<div class="flash-message flash-<?= $flash['type'] ?>" id="flashMsg">
    <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
    <?= sanitize($flash['message']) ?>
    <button onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<main class="main-content">