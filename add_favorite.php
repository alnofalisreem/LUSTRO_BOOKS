<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to use favorites.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Sign-in');
    exit;
}

$userId = intval($_SESSION['user_id']);
$offerId = intval($_POST['offer_id'] ?? 0);
$back = $_POST['back'] ?? '/lustro_books/';

if ($offerId > 0) {
    $check = $conn->prepare('SELECT id FROM favorites WHERE user_id = ? AND offer_id = ?');
    $check->bind_param('ii', $userId, $offerId);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        $add = $conn->prepare('INSERT INTO favorites (user_id, offer_id, creation_date) VALUES (?, ?, CURDATE())');
        $add->bind_param('ii', $userId, $offerId);
        $add->execute();
        $_SESSION['flash'] = 'Book added to favorites.';
    } else {
        $_SESSION['flash'] = 'This book is already in your favorites.';
    }
}

header('Location: ' . $back);
exit;
