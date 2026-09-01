<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['user_id'])) {
    $current_page = basename($_SERVER['PHP_SELF']);
    if (!in_array($current_page, ['login.php', 'recuperar_contrasena.php', 'restablecer_contrasena.php', 'restablecer_contrasena_dueno.php'], true)) {
        header("Location: login.php");
        exit;
    }
}
