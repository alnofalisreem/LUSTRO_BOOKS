<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email == '' || $password == '') {
    $_SESSION['login_error'] = 'Please enter your email and password.';
    header('Location: /lustro_books/Sign-in');
    exit;
}

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$correctPassword = false;

if ($user && password_verify($password, $user['password'])) {
    $correctPassword = true;
}

if (!$user || !$correctPassword) {
    $_SESSION['login_error'] = 'Incorrect email or password.';
    header('Location: /lustro_books/Sign-in');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['firstName'] . ' ' . $user['lastName'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['can_sell'] = intval($user['can_sell'] ?? 0);

header('Location: /lustro_books/');
exit;
?>
