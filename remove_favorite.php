<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/Sign-in');
    exit;
}

$userId = intval($_SESSION['user_id']);
$offerId = intval($_POST['offer_id'] ?? 0);
$back = $_POST['back'] ?? '/lustro_books/My-Favorites';
$stmt = $conn->prepare('DELETE FROM favorites WHERE user_id = ? AND offer_id = ?');
$stmt->bind_param('ii', $userId, $offerId);
$stmt->execute();

$_SESSION['flash'] = 'Book removed from favorites.';
header('Location: ' . $back);
exit;
