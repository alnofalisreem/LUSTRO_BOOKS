<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to view your favorites.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/Sign-in');
    exit;
}

require_once 'connection.php';
$pageTitle = 'My Favorites | LUSTRO BOOKS';
$userId = intval($_SESSION['user_id']);
$result = mysqli_query($conn, "SELECT books.*, categories.title AS category_name
    FROM favorites
    JOIN books ON favorites.offer_id = books.id
    JOIN categories ON books.cat_id = categories.id
    WHERE favorites.user_id = $userId AND books.is_active = 1
    ORDER BY favorites.id DESC");
include 'header.php';
?>

<section class="books-section favorites-page">
    

    <?php if ($result && mysqli_num_rows($result) > 0) { ?>
        <div class="books-title seller-books-title">
            <h2>My Favorites</h2>
        </div>
        <div class="books-grid">
            <?php while ($book = mysqli_fetch_assoc($result)) {
                $price = $book['org_price'] - ($book['org_price'] * $book['discount'] / 100);
            ?>
                <div class="book-card">
                    <div class="book-image">
                        <?php if (($book['book_condition'] ?? null) === 'used') { ?>
                            <span class="used-book-badge">Used</span>
                        <?php } ?>
                        <a href="/lustro_books/Books/<?php echo $book['id']; ?>">
                            <img src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                        </a>
                    </div>
                    <div class="book-info">
                        <small><?php echo htmlspecialchars($book['category_name']); ?></small>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <p class="book-price">SAR <?php echo number_format($price, 2); ?></p>
                        <a class="details-button" href="/lustro_books/Books/<?php echo $book['id']; ?>">View Details</a>
                        <form action="remove_favorite.php" method="POST">
                            <input type="hidden" name="offer_id" value="<?php echo $book['id']; ?>">
                            <button class="remove-button" type="submit">Remove</button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="empty-box">
            <h3>You haven’t added any favorites yet</h3>
            <p>Your favorite books will appear here.</p>
            <a href="/lustro_books/">Browse books</a>
        </div>
    <?php } ?>
</section>

<?php include 'footer.php'; ?>
