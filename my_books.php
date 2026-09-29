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
$permission = mysqli_query($conn, "SELECT can_sell FROM users WHERE id = $userId LIMIT 1");
$user = $permission ? mysqli_fetch_assoc($permission) : null;
if (!$user || intval($user['can_sell']) !== 1) {
    $_SESSION['flash'] = 'Your account is not enabled for selling books.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/');
    exit;
}

if (empty($_SESSION['delete_book_token'])) {
    $_SESSION['delete_book_token'] = bin2hex(random_bytes(32));
}

$filter = $_GET['status'] ?? 'all';
$allowedFilters = ['all', 'active', 'sold_out', 'removed'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// Count the seller's books by status and copies sold in non-cancelled orders.
$summaryStmt = $conn->prepare("SELECT
    COALESCE(SUM(CASE WHEN is_active = 1 AND quantity > 0 THEN 1 ELSE 0 END), 0) AS active_books,
    COALESCE(SUM(CASE WHEN is_active = 1 AND quantity = 0 THEN 1 ELSE 0 END), 0) AS sold_out_books,
    COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS removed_books,
    (SELECT COALESCE(SUM(order_items.quantity), 0)
        FROM order_items
        JOIN orders ON order_items.order_id = orders.id
        JOIN books sold_offer ON order_items.offer_id = sold_offer.id
        WHERE sold_offer.user_id = ? AND orders.status <> 'Cancelled') AS copies_sold
    FROM books
    WHERE user_id = ?");
$summaryStmt->bind_param('ii', $userId, $userId);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();

$where = 'books.user_id = ?';
if ($filter === 'active') {
    $where .= ' AND books.is_active = 1 AND books.quantity > 0';
} elseif ($filter === 'sold_out') {
    $where .= ' AND books.is_active = 1 AND books.quantity = 0';
} elseif ($filter === 'removed') {
    $where .= ' AND books.is_active = 0';
}


$stmt = $conn->prepare("SELECT books.*, categories.title AS category_name,
    COALESCE(book_sales.copies_sold, 0) AS copies_sold
    FROM books
    JOIN categories ON books.cat_id = categories.id
    LEFT JOIN (
        SELECT order_items.offer_id,
            SUM(order_items.quantity) AS copies_sold
        FROM order_items
        JOIN orders ON order_items.order_id = orders.id
        WHERE orders.status <> 'Cancelled'
        GROUP BY order_items.offer_id
    ) AS book_sales ON books.id = book_sales.offer_id
    WHERE $where
    ORDER BY books.id DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$emptyMessages = [
    'all' => ['You have not published any books yet', 'Your published books and their sales history will appear here.'],
    'active' => ['No active books', 'Books currently available for sale will appear here.'],
    'sold_out' => ['No sold-out books', 'Books with no remaining stock will appear here.'],
    'removed' => ['No removed books', 'Books removed from the store will appear here.']
];

$pageTitle = 'My Books | LUSTRO BOOKS';
include 'header.php';
?>

<main class="books-section my-books-page">
    <div class="books-title seller-books-title">
        <h2>My Books</h2>
        <a class="details-button" href="/lustro_books/Publish-a-Book">Publish a Book</a>
    </div>

    <section class="seller-summary" aria-label="Books summary">
        <a href="/lustro_books/My-Books?status=active" class="seller-summary-card<?php echo $filter === 'active' ? ' selected' : ''; ?>">
            <span>Active Listings</span>
            <strong><?php echo intval($summary['active_books']); ?></strong>
        </a>
        <a href="/lustro_books/My-Books?status=sold_out" class="seller-summary-card<?php echo $filter === 'sold_out' ? ' selected' : ''; ?>">
            <span>Sold Out</span>
            <strong><?php echo intval($summary['sold_out_books']); ?></strong>
        </a>
        <a href="/lustro_books/My-Books?status=removed" class="seller-summary-card<?php echo $filter === 'removed' ? ' selected' : ''; ?>">
            <span>Removed</span>
            <strong><?php echo intval($summary['removed_books']); ?></strong>
        </a>
        <div class="seller-summary-card">
            <span>Copies Sold</span>
            <strong><?php echo intval($summary['copies_sold']); ?></strong>
        </div>
    </section>

    <nav class="seller-filters" aria-label="Filter books">
        <a href="/lustro_books/My-Books" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All Books</a>
        <a href="/lustro_books/My-Books?status=active" class="<?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
        <a href="/lustro_books/My-Books?status=sold_out" class="<?php echo $filter === 'sold_out' ? 'active' : ''; ?>">Sold Out</a>
        <a href="/lustro_books/My-Books?status=removed" class="<?php echo $filter === 'removed' ? 'active' : ''; ?>">Removed</a>
    </nav>

    <?php if ($result->num_rows > 0) { ?>
        <div class="books-grid seller-books-grid">
            <?php while ($book = $result->fetch_assoc()) {
                $isRemoved = intval($book['is_active']) === 0;
                $isSoldOut = !$isRemoved && intval($book['quantity']) === 0;
                if ($isRemoved) {
                    $statusText = 'Removed';
                    $statusClass = 'removed';
                } elseif ($isSoldOut) {
                    $statusText = 'Sold Out';
                    $statusClass = 'sold-out';
                } else {
                    $statusText = 'Active';
                    $statusClass = 'active';
                }
            ?>
                <article class="book-card<?php echo $isRemoved ? ' removed-book' : ''; ?>">
                    <div class="book-image">
                        <?php if ($isRemoved) { ?>
                            <img class="removed-book-image" src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                        <?php } else { ?>
                            <a href="/lustro_books/Books/<?php echo intval($book['id']); ?>"><img src="<?php echo htmlspecialchars($book['image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>"></a>
                        <?php } ?>
                        <span class="listing-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        <span class="stock-badge"><?php echo intval($book['quantity']); ?> in stock</span>
                    </div>
                    <div class="book-info">
                        <small><?php echo htmlspecialchars($book['category_name']); ?></small>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>

                        <div class="seller-book-metrics">
                            <span><strong><?php echo intval($book['copies_sold']); ?></strong> copies sold</span>
                        </div>

                        <?php if (!$isRemoved) { ?>
                            <div class="seller-book-actions">
                                <a class="details-button" href="/lustro_books/Books/<?php echo intval($book['id']); ?>">View Details</a>
                                <form action="delete_book.php" method="POST" onsubmit="return confirm('Delete this book? Any pending orders containing it will be cancelled in full.');">
                                    <input type="hidden" name="offer_id" value="<?php echo intval($book['id']); ?>">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['delete_book_token']); ?>">
                                    <button class="delete-book-button" type="submit">Delete Book</button>
                                </form>
                            </div>
                        <?php } else { ?>
                            <p class="removed-book-note">This book is no longer visible in the store.</p>
                        <?php } ?>
                    </div>
                </article>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="empty-box seller-empty-box">
            <h3><?php echo htmlspecialchars($emptyMessages[$filter][0]); ?></h3>
            <p><?php echo htmlspecialchars($emptyMessages[$filter][1]); ?></p>

        </div>
    <?php } ?>
</main>

<?php include 'footer.php'; ?>