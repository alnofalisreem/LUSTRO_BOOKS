<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
session_start();
$_SESSION['flash'] = 'You have been logged out.';
$_SESSION['flash_type'] = 'success';
header('Location: /lustro_books/');
exit;
