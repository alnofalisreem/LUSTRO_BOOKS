<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Save the book ID in the session; opening another book replaces it
if (isset($_GET['id'])) {
    $_SESSION['selected_book_id'] = intval($_GET['id']);
    header('Location: /lustro_books/Book-Details');
    exit;
}

require_once 'connection.php';
$pageTitle = 'Book Details | LUSTRO BOOKS';
include 'header.php';
$id = intval($_SESSION['selected_book_id'] ?? 0);
$sql = "SELECT books.*, categories.title AS category_title
        FROM books
        JOIN categories ON books.cat_id = categories.id
        WHERE books.id = $id AND books.is_active = 1";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    echo '<div class="simple-page"><div class="empty-box"><h3>Book not found</h3><a href="/lustro_books/">Back to books</a></div></div>';
    include 'footer.php';
    exit;
}

$book = mysqli_fetch_assoc($result);
$price = $book['org_price'] - ($book['org_price'] * $book['discount'] / 100);
$out = intval($book['quantity']) <= 0;
?>

<div class="book-page">
    <div class="book-details-card">
        <div class="details-image">
                        <?php if (($book['book_condition'] ?? null) === 'used') { ?>
                            <span class="used-book-badge">Used</span>
                        <?php } ?>
            <img src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
        </div>

        <div class="details-info">
            <p class="details-label">BOOK DETAILS</p>
            <p class="details-category"><?php echo htmlspecialchars($book['category_title']); ?></p>
            <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                        <?php if ((int) $book['user_id'] !== 26) { ?>
                            <span class="seller-source-badge">Independent seller</span>
                        <?php } ?>
            <?php if (in_array($book['book_condition'] ?? null, ['new', 'used'], true)) { ?>
                <p class="details-category">Condition: <?php echo $book['book_condition'] === 'new' ? 'New' : 'Used'; ?></p>
            <?php } ?>
            <p class="details-description"><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>

            <div class="details-price">
                <span class="new-price">SAR <?php echo number_format($price, 2); ?></span>
                <?php if ($book['discount'] > 0) { ?>
                    <span class="old-price">SAR <?php echo number_format($book['org_price'], 2); ?></span>
                    <span class="save-text">Save <?php echo intval($book['discount']); ?>%</span>
                <?php } ?>
            </div>

            <?php if (!$out) { ?>
                <form action="add_to_cart.php" method="POST">
                    <input type="hidden" name="offer_id" value="<?php echo $book['id']; ?>">
                    <input type="hidden" name="back" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                    <div class="quantity-box">
                        <span>Quantity</span>
                        <button type="button" onclick="changeQuantity(-1)">-</button>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo intval($book['quantity']); ?>" readonly>
                        <button type="button" onclick="changeQuantity(1)">+</button>
                    </div>
                    <button class="add-cart-button" type="submit">Add to Cart</button>
                </form>
                <p class="stock-text"><?php echo intval($book['quantity']); ?> copies available</p>
            <?php } else { ?>
                <button class="add-cart-button disabled-button" type="button" disabled>Out of Stock</button>
            <?php } ?>

            <a class="back-link" href="/lustro_books/">Back to all books</a>
        </div>
    </div>
</div>

<script>
function changeQuantity(number) {
    var input = document.getElementById('quantity');
    var value = parseInt(input.value);
    var max = parseInt(input.max);

    if (number == 1 && value < max) {
        input.value = value + 1;
    }

    if (number == -1 && value > 1) {
        input.value = value - 1;
    }
}
</script>

<?php include 'footer.php'; ?>
