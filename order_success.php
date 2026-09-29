<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/Sign-in');
    exit;
}

require_once 'connection.php';
$orderId = intval($_GET['order'] ?? $_SESSION['confirmed_order_id'] ?? 0);
$userId = intval($_SESSION['user_id']);
$stmt = $conn->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    header('Location: /lustro_books/My-Orders');
    exit;
}
$stmt->close();
$_SESSION['confirmed_order_id'] = $orderId;
if (isset($_GET['order'])) {
    header('Location: /lustro_books/Order-Confirmed');
    exit;
}
$pageTitle = 'Order Confirmed | LUSTRO BOOKS';
include 'header.php';
?>

<main class="books-section order-success-page">
    <section class="order-success-card">

        <h1>Thank you for your order</h1>
        <p class="order-success-message">Your order has been placed successfully. You can follow its status from your orders page.</p>

        <div class="success-order-number">
            <span>Order Number</span>
            <strong>#<?php echo $orderId; ?></strong>
        </div>

        <div class="order-success-actions">
            <a class="success-primary-action" href="/lustro_books/My-Orders">View My Orders</a>
            <a class="success-secondary-action" href="/lustro_books/">Continue Shopping</a>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>