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
    header('Location: /lustro_books/Checkout');
    exit;
}

$userId = intval($_SESSION['user_id']);
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $mobile === '' || $address === '') {
    $_SESSION['flash'] = 'Please complete all customer information correctly.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Checkout');
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
// Save the order and update stock in one transaction
$conn->begin_transaction();

try {
    // Lock the selected rows while confirming the order
    $cartStmt = $conn->prepare('SELECT cart_items.offer_id, cart_items.quantity AS cart_quantity,
        books.org_price, books.discount, books.quantity AS stock_quantity
        FROM cart_items
        JOIN books ON cart_items.offer_id = books.id
        WHERE cart_items.user_id = ? AND books.is_active = 1 AND books.quantity > 0
        FOR UPDATE');
    $cartStmt->bind_param('i', $userId);
    $cartStmt->execute();
    $cartItems = $cartStmt->get_result();

    if ($cartItems->num_rows === 0) {
        throw new RuntimeException('No books in your cart are currently available. Please review your cart.');
    }

    $items = [];
    $total = 0;

    while ($item = $cartItems->fetch_assoc()) {
        $requestedQuantity = intval($item['cart_quantity']);
        $stockQuantity = intval($item['stock_quantity']);

        // Go back to the cart if there is not enough stock
        if ($requestedQuantity < 1 || $requestedQuantity > $stockQuantity) {
            throw new RuntimeException('A book in your cart no longer has the requested quantity. Please review your cart.');
        }

        $price = floatval($item['org_price']);
        $discount = intval($item['discount']);
        if ($discount > 0) {
            $price -= $price * $discount / 100;
        }
        $price = round($price, 2);
        $total += $price * $requestedQuantity;

        $items[] = [
            'offer_id' => intval($item['offer_id']),
            'quantity' => $requestedQuantity,
            'price' => $price
        ];
    }

    $total = round($total, 2);
    $status = 'Pending';
    $orderStmt = $conn->prepare('INSERT INTO orders
        (user_id, full_name, email, phone, address, total_price, status, order_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $orderStmt->bind_param('issssds', $userId, $fullName, $email, $mobile, $address, $total, $status);
    $orderStmt->execute();
    $orderId = $orderStmt->insert_id;

    $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, offer_id, quantity, price) VALUES (?, ?, ?, ?)');
    $stockStmt = $conn->prepare('UPDATE books
        SET quantity = quantity - ?
        WHERE id = ? AND is_active = 1 AND quantity >= ?');

    foreach ($items as $item) {
        $offerId = $item['offer_id'];
        $quantity = $item['quantity'];
        $itemPrice = $item['price'];

        $itemStmt->bind_param('iiid', $orderId, $offerId, $quantity, $itemPrice);
        $itemStmt->execute();

        $stockStmt->bind_param('iii', $quantity, $offerId, $quantity);
        $stockStmt->execute();
        // Stop and roll back if the book stock was not updated
        if ($stockStmt->affected_rows !== 1) {
            throw new RuntimeException('A book became unavailable before the order was completed. Please review your cart.');
        }
    }

    // Remove only the ordered books from the cart
    $clearCart = $conn->prepare('DELETE FROM cart_items WHERE user_id = ? AND offer_id = ?');
    foreach ($items as $item) {
        $offerId = $item['offer_id'];
        $clearCart->bind_param('ii', $userId, $offerId);
        $clearCart->execute();
    }

    $conn->commit();
    if (!send_order_email($conn, $orderId, 'confirmed')) {
        $_SESSION['flash'] = 'Your order is confirmed, but we could not send the confirmation email. You can view your order in My Orders.';
        $_SESSION['flash_type'] = 'error';
    }
    $_SESSION['confirmed_order_id'] = $orderId;
    header('Location: /lustro_books/Order-Confirmed');
    exit;
} catch (Throwable $error) {
    // Undo the transaction changes when we reach this error handler
    $conn->rollback();
    $_SESSION['flash'] = $error instanceof RuntimeException
        ? $error->getMessage()
        : 'The order could not be completed. Please try again.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/My-Cart');
    exit;
}
