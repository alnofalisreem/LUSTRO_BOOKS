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
$action = $_POST['action'] ?? '';

if ($action == 'remove') {
    $stmt = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND offer_id = ?');
    $stmt->bind_param('ii', $userId, $offerId);
    $stmt->execute();
} elseif ($action === 'update') {
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $stockResult = mysqli_query($conn, "SELECT quantity FROM books WHERE id = $offerId AND is_active = 1");
    $book = $stockResult ? mysqli_fetch_assoc($stockResult) : null;
    $stock = $book ? max(0, intval($book['quantity'])) : 0;
    if ($stock === 0) {
        $_SESSION['flash'] = 'This book is out of stock and is not included in your order.';
        $_SESSION['flash_type'] = 'error';
        header('Location: /lustro_books/My-Cart');
        exit;
    }
    if ($quantity > $stock) {
        $_SESSION['flash'] = 'Only ' . $stock . ' ' . ($stock === 1 ? 'copy' : 'copies') . ' available. Your cart has been updated.';
        unset($_SESSION['flash_type']);
    }
    $quantity = min($quantity, $stock);
    $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND offer_id = ?');
    $stmt->bind_param('iii', $quantity, $userId, $offerId);
    $stmt->execute();
}

header('Location: /lustro_books/My-Cart');
exit;
