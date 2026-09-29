<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to view your cart.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Sign-in');
    exit;
}

require_once 'connection.php';
$pageTitle = 'My Cart | LUSTRO BOOKS';
$userId = intval($_SESSION['user_id']);
$result = mysqli_query($conn, "SELECT cart_items.quantity AS cart_quantity, books.*, categories.title AS category_name
    FROM cart_items
    JOIN books ON cart_items.offer_id = books.id
    JOIN categories ON books.cat_id = categories.id
    WHERE cart_items.user_id = $userId AND books.is_active = 1
    ORDER BY cart_items.id DESC");
$total = 0;
$availableItems = 0;
$syncQuantity = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND offer_id = ?');
include 'header.php';
?>

<main class="books-section cart-page">
    

    <?php if ($result && mysqli_num_rows($result) > 0) { ?>
       <div class="books-title seller-books-title">
            <h2>My Cart</h2>
        </div>
        <div class="cart-list">
            <?php while ($book = mysqli_fetch_assoc($result)) {
                $stock = max(0, intval($book['quantity']));
                $outOfStock = $stock === 0;
                $quantityChanged = !$outOfStock && intval($book['cart_quantity']) > $stock;
                // Update the cart quantity if stock has dropped
                if ($quantityChanged) {
                    $book['cart_quantity'] = $stock;
                    $syncQuantity->bind_param('iii', $stock, $userId, $book['id']);
                    $syncQuantity->execute();
                }
                if (!$outOfStock) {
                    $availableItems++;
                }
                $price = $book['org_price'] - ($book['org_price'] * $book['discount'] / 100);
                // Keep sold-out books visible but leave them out of the total
                $lineTotal = $outOfStock ? 0 : $price * intval($book['cart_quantity']);
                $total += $lineTotal;
            ?>
                <div class="cart-item">
                    <img src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                    <div class="cart-info">
                        <small><?php echo htmlspecialchars($book['category_name']); ?></small>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <?php if ((int) $book['user_id'] !== 26) { ?>
                            <span class="seller-source-badge">Independent seller</span>
                        <?php } ?>
                        <p>SAR <?php echo number_format($price, 2); ?></p>
                        <?php if ($outOfStock) { ?>
                            <p class="cart-stock-notice" role="status">Out of Stock — not included in your total or order.</p>
                        <?php } elseif ($quantityChanged) { ?>
                            <p class="cart-stock-notice" role="status">Only <?php echo $stock; ?> <?php echo $stock === 1 ? 'copy' : 'copies'; ?> available. Your cart has been updated.</p>
                        <?php } ?>
                    </div>
                    <form class="cart-form" action="update_cart.php" method="POST">
                        <input type="hidden" name="offer_id" value="<?php echo $book['id']; ?>">
                        <input type="number" name="quantity" aria-label="Quantity" min="1" max="<?php echo $stock; ?>" value="<?php echo $outOfStock ? 0 : intval($book['cart_quantity']); ?>" <?php echo $outOfStock ? 'disabled' : ''; ?>>
                        <button type="submit" name="action" value="update" <?php echo $outOfStock ? 'disabled' : ''; ?>>Update</button>
                        <button class="remove-button" type="submit" name="action" value="remove" formnovalidate>Remove</button>
                    </form>
                    <strong>SAR <?php echo number_format($lineTotal, 2); ?></strong>
                </div>
            <?php } ?>
</div>
        <div class="cart-total">

    <div class="total-row">
        <span>Total:</span>
        <span>SAR <?php echo number_format($total, 2); ?></span>
    </div>

    <?php if ($availableItems > 0) { ?>
        <a class="details-button" href="/lustro_books/Checkout">Checkout</a>
    <?php } else { ?>
        <p class="cart-stock-notice">No books in your cart are currently available.</p>
        <a class="details-button" href="/lustro_books/">Continue shopping</a>
    <?php } ?>

</div>
    <?php } else { ?>
        <div class="empty-box">
            <h3>Your cart is waiting for its first book</h3>
            <p>The books you add to your cart will appear here.</p>
            <a href="/lustro_books/">Continue shopping</a>
        </div>
    <?php } ?>
</main>

<?php include 'footer.php'; ?>
