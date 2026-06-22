<?php
require_once __DIR__ . '/includes/functions.php';
session_destroy();
header('Location: ' . SITE_URL . '/login.php');
exit();