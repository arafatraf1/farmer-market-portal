<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
set_flash('info', 'You have been signed out safely. Thank you for visiting Farmer Market Portal.');
header('Location: ' . url('login.php'));
exit;
