<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';
require_once 'send_order_email.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/Sign-in');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lustro_books/My-Orders');
    exit;
}

// Check the form token against the session token
$token = $_POST['token'] ?? '';
if (empty($_SESSION['cancel_order_token']) || !hash_equals($_SESSION['cancel_order_token'], $token)) {
    $_SESSION['flash'] = 'Your request could not be verified. Please try again.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/My-Orders');
    exit;
}

$userId = intval($_SESSION['user_id']);
$orderId = intval($_POST['order_id'] ?? 0);

$conn->begin_transaction();

try {
    // Only the owner can cancel a pending order within 24 hours
    $orderStmt = $conn->prepare("SELECT id FROM orders
        WHERE id = ? AND user_id = ? AND status = 'Pending'
        AND order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        FOR UPDATE");
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new RuntimeException('This order can no longer be cancelled.');
    }

    $itemsStmt = $conn->prepare('SELECT offer_id, quantity FROM order_items WHERE order_id = ?');
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result();

    // Return all book quantities to stock before changing the status
    $restoreStmt = $conn->prepare('UPDATE books SET quantity = quantity + ? WHERE id = ?');
    while ($item = $items->fetch_assoc()) {
        $quantity = intval($item['quantity']);
        $offerId = intval($item['offer_id']);
        $restoreStmt->bind_param('ii', $quantity, $offerId);
        $restoreStmt->execute();
    }

    $updateStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param('ii', $orderId, $userId);
    $updateStmt->execute();

    $conn->commit();
    $emailSent = send_order_email($conn, $orderId, 'customer_cancelled');
    $_SESSION['flash'] = 'Your order has been cancelled.';
    if (!$emailSent) {
        $_SESSION['flash'] .= ' We could not send the cancellation email. You can check the order status in My Orders.';
    }
    $_SESSION['flash_type'] = 'success';
} catch (Throwable $error) {
    $conn->rollback();
    $_SESSION['flash'] = $error instanceof RuntimeException
        ? $error->getMessage()
        : 'The order could not be cancelled. Please try again.';
    $_SESSION['flash_type'] = 'error';
}

header('Location: /lustro_books/My-Orders');
exit;
