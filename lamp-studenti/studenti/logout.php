<?php
session_start();

// Golim variabilele de sesiune
$_SESSION = [];

// Ștergem cookie-ul de sesiune dacă există
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Ștergem cookie-ul remember (dacă există)
if (!empty($_COOKIE['remember'])) {
    setcookie('remember', '', time() - 3600, '/');
}

// Distrugem sesiunea și redirecționăm
session_destroy();
header('Location: login.php');
exit;
?>