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
    header('Location: /lustro_books/My-Books');
    exit;
}

$token = $_POST['token'] ?? '';
if (empty($_SESSION['delete_book_token']) || !hash_equals($_SESSION['delete_book_token'], $token)) {
    $_SESSION['flash'] = 'Your request could not be verified. Please try again.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/My-Books');
    exit;
}

$userId = intval($_SESSION['user_id']);
$bookId = intval($_POST['offer_id'] ?? 0);
$conn->begin_transaction();

try {
    $bookStmt = $conn->prepare('SELECT id FROM books WHERE id = ? AND user_id = ? AND is_active = 1 FOR UPDATE');
    $bookStmt->bind_param('ii', $bookId, $userId);
    $bookStmt->execute();

    if (!$bookStmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('The book was not found or has already been deleted.');
    }

    
    // Find pending orders with this book and cancel them in full
    $ordersStmt = $conn->prepare("SELECT id FROM orders
        WHERE status = 'Pending'
        AND EXISTS (SELECT 1 FROM order_items WHERE order_items.order_id = orders.id AND order_items.offer_id = ?)
        ORDER BY id FOR UPDATE");
    $ordersStmt->bind_param('i', $bookId);
    $ordersStmt->execute();
    $pendingOrders = $ordersStmt->get_result();
    $itemsStmt = $conn->prepare('SELECT offer_id, quantity FROM order_items WHERE order_id = ? ORDER BY offer_id');
    $restoreStmt = $conn->prepare('UPDATE books SET quantity = quantity + ? WHERE id = ?');
    $cancelStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
    $cancelledOrderCount = 0;
    $cancelledOrderIds = [];

    
    while ($order = $pendingOrders->fetch_assoc()) {
        $orderId = intval($order['id']);
        $itemsStmt->bind_param('i', $orderId);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result();
        // Return the quantity of every book in the order
        while ($item = $items->fetch_assoc()) {
            $quantity = intval($item['quantity']);
            $orderBookId = intval($item['offer_id']);
            $restoreStmt->bind_param('ii', $quantity, $orderBookId);
            $restoreStmt->execute();
        }
        $cancelStmt->bind_param('i', $orderId);
        $cancelStmt->execute();
        $cancelledOrderCount++;
        $cancelledOrderIds[] = $orderId;
    }
    $removeFavorites = $conn->prepare('DELETE FROM favorites WHERE offer_id = ?');
    $removeFavorites->bind_param('i', $bookId);
    $removeFavorites->execute();

    $removeCart = $conn->prepare('DELETE FROM cart_items WHERE offer_id = ?');
    $removeCart->bind_param('i', $bookId);
    $removeCart->execute();

    
    // Hide the book but keep its details for old orders
    $hideBook = $conn->prepare('UPDATE books SET is_active = 0 WHERE id = ? AND user_id = ?');
    $hideBook->bind_param('ii', $bookId, $userId);
    $hideBook->execute();

    $conn->commit();
    $failedEmailCount = 0;
    foreach ($cancelledOrderIds as $cancelledOrderId) {
        if (!send_order_email($conn, $cancelledOrderId, 'seller_cancelled')) {
            $failedEmailCount++;
        }
    }
    $_SESSION['flash'] = 'The book has been deleted from the store.';
    if ($cancelledOrderCount > 0) {
        $_SESSION['flash'] .= ' Related pending orders have been cancelled.';
    }
    if ($failedEmailCount > 0) {
        $_SESSION['flash'] .= ' Some cancellation emails could not be sent.';
    }
    $_SESSION['flash_type'] = 'success';
} catch (Throwable $error) {
    $conn->rollback();
    if ($error instanceof RuntimeException) {
        $_SESSION['flash'] = $error->getMessage();
    } else {
        $_SESSION['flash'] = 'The book could not be deleted. Please try again.';
    }
    $_SESSION['flash_type'] = 'error';
}

header('Location: /lustro_books/My-Books');
exit;
