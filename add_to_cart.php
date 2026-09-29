<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to add books to your cart.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Sign-in');
    exit;
}

$userId = intval($_SESSION['user_id']);
$offerId = intval($_POST['offer_id'] ?? 0);
$quantity = max(1, intval($_POST['quantity'] ?? 1));

$bookResult = mysqli_query($conn, "SELECT quantity, is_active FROM books WHERE id = $offerId");
$book = $bookResult ? mysqli_fetch_assoc($bookResult) : null;

if (!$book || intval($book['is_active']) !== 1 || intval($book['quantity']) < 1) {
    $_SESSION['flash'] = 'This book is out of stock.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Books/' . $offerId);
    exit;
}

$quantity = min($quantity, intval($book['quantity']));
$check = $conn->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND offer_id = ?');
$check->bind_param('ii', $userId, $offerId);
$check->execute();
$row = $check->get_result()->fetch_assoc();

if ($row) {
    // Add to the current quantity without going over the stock
    $newQuantity = min(intval($row['quantity']) + $quantity, intval($book['quantity']));
    $update = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
    $update->bind_param('ii', $newQuantity, $row['id']);
    $update->execute();
} else {
    $add = $conn->prepare('INSERT INTO cart_items (user_id, offer_id, quantity, creation_date) VALUES (?, ?, ?, CURDATE())');
    $add->bind_param('iii', $userId, $offerId, $quantity);
    $add->execute();
}

$_SESSION['flash'] = 'Book added to cart.';
$back = $_POST['back'] ?? '';

if ($back !== '' && !preg_match('/[\r\n]/', $back)) {
    $backParts = parse_url($back);
    if ($backParts !== false && empty($backParts['scheme']) && empty($backParts['host'])) {
        header('Location: ' . $back);
        exit;
    }
}

header('Location: /lustro_books/My-Cart');
exit;
