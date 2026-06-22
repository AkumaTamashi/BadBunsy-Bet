<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$currentAdmin = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-sidebar">
    <p class="admin-menu-title">Panel Admin</p>
    <a href="<?= SITE_URL ?>/admin/index.php" class="admin-menu-link <?= $currentAdmin === 'index.php' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    
    <p class="admin-menu-title" style="margin-top:16px;">Partidos</p>
    <a href="<?= SITE_URL ?>/admin/partidos.php" class="admin-menu-link <?= $currentAdmin === 'partidos.php' ? 'active' : '' ?>">
        <i class="fas fa-futbol"></i> Gestionar Partidos
    </a>
    <a href="<?= SITE_URL ?>/admin/crear_partido.php" class="admin-menu-link <?= $currentAdmin === 'crear_partido.php' ? 'active' : '' ?>">
        <i class="fas fa-plus-circle"></i> Crear Partido
    </a>
    <a href="<?= SITE_URL ?>/admin/resultados.php" class="admin-menu-link <?= $currentAdmin === 'resultados.php' ? 'active' : '' ?>">
        <i class="fas fa-check-double"></i> Cargar Resultados
    </a>
    
    <p class="admin-menu-title" style="margin-top:16px;">Usuarios</p>
    <a href="<?= SITE_URL ?>/admin/usuarios.php" class="admin-menu-link <?= $currentAdmin === 'usuarios.php' ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Gestionar Usuarios
    </a>
    
    <p class="admin-menu-title" style="margin-top:16px;">Apuestas</p>
    <a href="<?= SITE_URL ?>/admin/apuestas.php" class="admin-menu-link <?= $currentAdmin === 'apuestas.php' ? 'active' : '' ?>">
        <i class="fas fa-ticket-alt"></i> Ver Apuestas
    </a>
    
    <p class="admin-menu-title" style="margin-top:16px;">Sistema</p>
    <a href="<?= SITE_URL ?>/admin/configuracion.php" class="admin-menu-link <?= $currentAdmin === 'configuracion.php' ? 'active' : '' ?>">
        <i class="fas fa-sliders-h"></i> Configuración
    </a>

    <div style="border-top:1px solid var(--border);margin:16px 0;"></div>
    <a href="<?= SITE_URL ?>/index.php" class="admin-menu-link">
        <i class="fas fa-arrow-left"></i> Volver al sitio
    </a>
</div>