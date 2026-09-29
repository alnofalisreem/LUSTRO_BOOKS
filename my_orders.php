<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to view your orders.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Sign-in');
    exit;
}

if (empty($_SESSION['cancel_order_token'])) {
    $_SESSION['cancel_order_token'] = bin2hex(random_bytes(32));
}

$userId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT id, total_price, status, order_date,
    (status = 'Pending' AND order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS can_cancel
    FROM orders
    WHERE user_id = ?
    ORDER BY order_date DESC, id DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$orders = $stmt->get_result();

$pageTitle = 'My Orders | LUSTRO BOOKS';
include 'header.php';
?>

<main class="books-section orders-page">
    <?php if ($orders && $orders->num_rows > 0) { ?>
        <div class="books-title seller-books-title">
            <h2>My Orders</h2>
        </div>

        <div class="orders-list">
            <?php while ($order = $orders->fetch_assoc()) {
                $itemsStmt = $conn->prepare('SELECT order_items.quantity, order_items.price, books.title, books.image
                    FROM order_items
                    LEFT JOIN books ON order_items.offer_id = books.id
                    WHERE order_items.order_id = ?
                    ORDER BY order_items.id');
                $itemsStmt->bind_param('i', $order['id']);
                $itemsStmt->execute();
                $items = $itemsStmt->get_result();
                $statusClass = strtolower(preg_replace('/[^a-zA-Z]/', '', $order['status']));
            ?>
                <article class="order-card">
                    <header class="order-card-header">
                        <div>
                            <span class="order-label">Order</span>
                            <h3>#<?php echo intval($order['id']); ?></h3>
                        </div>
                        <div class="order-date">
                            <span class="order-label">Placed on</span>
                            <strong><?php echo date('M j, Y · g:i A', strtotime($order['order_date'])); ?></strong>
                        </div>
                        <span class="order-status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                    </header>

                    <div class="order-items">
                        <?php while ($item = $items->fetch_assoc()) { ?>
                            <div class="order-item">
                                <?php if (!empty($item['image'])) { ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?? 'Book'); ?>">
                                <?php } ?>
                                <div>
                                    <h4><?php echo htmlspecialchars($item['title'] ?? 'Book no longer available'); ?></h4>
                                    <p>Quantity: <?php echo intval($item['quantity']); ?></p>
                                </div>
                                <strong>SAR <?php echo number_format(floatval($item['price']) * intval($item['quantity']), 2); ?></strong>
                            </div>
                        <?php } ?>
                    </div>

                    <footer class="order-card-footer">
                        <div class="order-total">
                            <span>Total</span>
                            <strong>SAR <?php echo number_format($order['total_price'], 2); ?></strong>
                        </div>
                        <?php if (intval($order['can_cancel']) === 1) { ?>
                            <form action="cancel_order.php" method="POST" onsubmit="return confirm('Cancel this order?');">
                                <input type="hidden" name="order_id" value="<?php echo intval($order['id']); ?>">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['cancel_order_token']); ?>">
                                <button class="cancel-order-button" type="submit">Cancel Order</button>
                            </form>
                        <?php } ?>
                    </footer>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="empty-box">
            <h3>You haven’t placed any orders yet</h3>
            <p>Your completed orders will appear here.</p>
            <a href="/lustro_books/">Browse books</a>
        </div>
    <?php } ?>
</main>

<?php include 'footer.php'; ?>