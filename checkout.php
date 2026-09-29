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
$userQuery = mysqli_query($conn, "SELECT * FROM users WHERE id = $userId LIMIT 1");
$user = mysqli_fetch_assoc($userQuery);

$cartQuery = mysqli_query($conn, "SELECT books.id, books.title, books.image, books.org_price, books.discount, cart_items.quantity
    FROM cart_items
    JOIN books ON cart_items.offer_id = books.id
    WHERE cart_items.user_id = $userId AND books.is_active = 1 AND books.quantity > 0");

if (!$cartQuery || mysqli_num_rows($cartQuery) === 0) {
    $_SESSION['flash'] = 'No books in your cart are currently available. Please review your cart.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/My-Cart');
    exit;
}

// Check stock again in case someone else bought the book
$stockCheck = mysqli_query($conn, "SELECT cart_items.id FROM cart_items
    JOIN books ON cart_items.offer_id = books.id
    WHERE cart_items.user_id = $userId AND books.is_active = 1
    AND books.quantity > 0 AND cart_items.quantity > books.quantity LIMIT 1");
if (!$stockCheck || mysqli_num_rows($stockCheck) > 0) {
    $_SESSION['flash'] = 'Available quantities have changed. Please review your cart before checkout.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/My-Cart');
    exit;
}

$total = 0;
$pageTitle = 'Checkout | LUSTRO BOOKS';
include 'header.php';
?>

<main class="books-section checkout-page">
    <div class="books-title seller-books-title">
        <h2>Checkout</h2>
    </div>

    <form action="confirm_order.php" method="POST">
        <section class="checkout-left">
            <div class="checkout-section-heading">
                <span>1</span>
                <div>
                    <h2>Customer Information</h2>
                    <p>Review your contact and delivery details.</p>
                </div>
            </div>

            <div class="checkout-fields">
                <div class="checkout-field">
                    <label for="checkout-first-name">First Name</label>
                    <input id="checkout-first-name" type="text" name="firstName" value="<?php echo htmlspecialchars($user['firstName']); ?>" required>
                </div>

                <div class="checkout-field">
                    <label for="checkout-last-name">Last Name</label>
                    <input id="checkout-last-name" type="text" name="lastName" value="<?php echo htmlspecialchars($user['lastName']); ?>" required>
                </div>

                <div class="checkout-field checkout-field-wide">
                    <label for="checkout-email">Email</label>
                    <input id="checkout-email" type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="checkout-field checkout-field-wide">
                    <label for="checkout-mobile">Phone Number</label>
                    <input id="checkout-mobile" type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                </div>

                <div class="checkout-field checkout-field-wide">
                    <label for="checkout-address">Short Address</label>
                    <input id="checkout-address" type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                </div>
            </div>
        </section>

        <aside class="checkout-right">
            <div class="checkout-section-heading">
                <span>2</span>
                <div>
                    <h2>Order Summary</h2>
                    <p>Confirm the books in your order.</p>
                    <p>Out-of-stock books stay in your cart and are not included in this order.</p>
                </div>
            </div>

            <div class="checkout-items">
                <?php while ($item = mysqli_fetch_assoc($cartQuery)) {
                    $price = $item['org_price'];
                    if ($item['discount'] > 0) {
                        $price -= $price * $item['discount'] / 100;
                    }
                    $subTotal = $price * intval($item['quantity']);
                    $total += $subTotal;
                ?>
                    <div class="checkout-book">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        <div class="checkout-book-info">
                            <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                            <p>Quantity: <?php echo intval($item['quantity']); ?></p>
                            <strong>SAR <?php echo number_format($subTotal, 2); ?></strong>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="checkout-total">
                <span>Total</span>
                <strong>SAR <?php echo number_format($total, 2); ?></strong>
            </div>

            <input type="hidden" name="total" value="<?php echo $total; ?>">
            <p class="checkout-demo-notice">This is a demo store. No payment will be taken.</p>
            <button type="submit" class="confirm-button">Confirm Order</button>
            <a class="checkout-back-link" href="/lustro_books/My-Cart">Back to cart</a>
        </aside>
    </form>
</main>

<?php include 'footer.php'; ?>