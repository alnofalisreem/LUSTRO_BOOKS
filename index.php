<?php
require_once 'connection.php';

$cat = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$title = 'All Books';

if ($cat > 0) {
    $catResult = mysqli_query($conn, "SELECT title FROM categories WHERE id = $cat");
    if ($catRow = mysqli_fetch_assoc($catResult)) {
        $title = $catRow['title'];
    }
}

if ($search !== '') {
    $title = 'Search Results';
}

$pageTitle = $title === 'All Books'
    ? 'Home | LUSTRO BOOKS'
    : $title . ' | LUSTRO BOOKS';
include 'header.php';

$favoriteSelect = $logged
    ? ", EXISTS(SELECT 1 FROM favorites WHERE favorites.user_id = $userId AND favorites.offer_id = books.id) AS is_favorite"
    : ", 0 AS is_favorite";
$sql = "SELECT books.*, categories.title AS category_name $favoriteSelect FROM books JOIN categories ON books.cat_id = categories.id WHERE books.is_active = 1";
if ($cat > 0) {
    $sql .= " AND books.cat_id = $cat";
}
if ($search != '') {
    $searchLength = function_exists('mb_strlen') ? mb_strlen($search, 'UTF-8') : strlen($search);

    if ($searchLength <= 2) {
        // For short searches like IT, match a whole word
        $pattern = '[[:<:]]' . preg_quote($search, '/') . '[[:>:]]';
        $safePattern = mysqli_real_escape_string($conn, $pattern);
        $sql .= " AND (books.title REGEXP '$safePattern' OR books.description REGEXP '$safePattern' OR categories.title REGEXP '$safePattern')";
    } else {
        $safeSearch = mysqli_real_escape_string($conn, $search);
        $sql .= " AND (books.title LIKE '%$safeSearch%' OR books.description LIKE '%$safeSearch%' OR categories.title LIKE '%$safeSearch%')";
    }
}
$sql .= " ORDER BY books.id DESC";
$result = mysqli_query($conn, $sql);
?>

<?php if ($cat == 0 && $search == '') { ?>

<section class="hero-slider" aria-label="Featured books">
    <div class="hero-track">
        <div class="welcome hero-slide">
            <div>
                <h1>Find your next favorite book</h1>
                <p>Travel without moving... Your next story begins here.</p>
                <span>Novels, children's books and useful books for students.</span>
                <a href="#books">Browse</a>
            </div>
        </div>
        <div class="click-books hero-slide">
            <h2>Try clicking on a book</h2>
            <div class="click-books-grid">
                <a class="click-book" href="/lustro_books/Books/51" aria-label="View My First Number Book details">
                    <img src="icon/featured-book-1.png" alt="My First Number Book">
                </a>
                <a class="click-book" href="/lustro_books/Books/54" aria-label="View Soldier details">
                    <img src="icon/featured-book-2.png?v=2" alt="Soldier">
                </a>
                <a class="click-book" href="/lustro_books/Books/35" aria-label="View Modern Architecture details">
                    <img src="icon/featured-book-3.png" alt="Modern Architecture">
                </a>
            </div>
        </div>
    </div>
    <button class="hero-arrow hero-prev" type="button" aria-label="Previous slide">&#10094;</button>
    <button class="hero-arrow hero-next" type="button" aria-label="Next slide">&#10095;</button>
    <div class="hero-dots" aria-label="Choose slide">
        <button class="active" type="button" aria-label="Show first slide"></button>
        <button type="button" aria-label="Show second slide"></button>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const slider = document.querySelector('.hero-slider');
    if (!slider) return;
    const track = slider.querySelector('.hero-track');
    const dots = slider.querySelectorAll('.hero-dots button');
    let currentSlide = 0;

    function showSlide(index) {
        currentSlide = (index + 2) % 2;
        track.style.transform = `translateX(-${currentSlide * 50}%)`;
        dots.forEach((dot, dotIndex) => dot.classList.toggle('active', dotIndex === currentSlide));
    }

    slider.querySelector('.hero-prev').addEventListener('click', () => showSlide(currentSlide - 1));
    slider.querySelector('.hero-next').addEventListener('click', () => showSlide(currentSlide + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => showSlide(index)));
});
</script>

<?php } ?>

<section class="books-section" id="books">
    <?php if (mysqli_num_rows($result) > 0) { ?>
        <div class="books-title">
            <div>
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <?php if ($search != '') { ?>
                    <p>Search for: <?php echo htmlspecialchars($search); ?></p>
                <?php } ?>
            </div>
            <span><?php echo mysqli_num_rows($result); ?> books</span>
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
                        <form action="<?php echo $book['is_favorite'] ? 'remove_favorite.php' : 'add_favorite.php'; ?>" method="POST">
                            <input type="hidden" name="offer_id" value="<?php echo $book['id']; ?>">
                            <input type="hidden" name="back" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                            <button class="favorite-button<?php echo $book['is_favorite'] ? ' active' : ''; ?>" type="submit" title="<?php echo $book['is_favorite'] ? 'Remove from favorites' : 'Add to favorites'; ?>" aria-label="<?php echo $book['is_favorite'] ? 'Remove from favorites' : 'Add to favorites'; ?>" aria-pressed="<?php echo $book['is_favorite'] ? 'true' : 'false'; ?>">
                                <img src="icon/<?php echo $book['is_favorite'] ? 'heart-fill.svg' : 'heart.svg'; ?>" alt="">
                            </button>
                        </form>
                        <?php if ($book['discount'] > 0) { ?>
                            <span class="discount">-<?php echo intval($book['discount']); ?>%</span>
                        <?php } ?>
                    </div>
                    <div class="book-info">
                        <small><?php echo htmlspecialchars($book['category_name']); ?></small>
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <?php if ((int) $book['user_id'] !== 26) { ?>
                            <span class="seller-source-badge">Independent seller</span>
                        <?php } ?>
                        <p class="book-price">SAR <?php echo number_format($price, 2); ?></p>
                        <?php if ($book['discount'] > 0) { ?>
                            <p class="before-price">SAR <?php echo number_format($book['org_price'], 2); ?></p>
                        <?php } ?>
                        <form class="card-cart-form" action="add_to_cart.php" method="POST">
                            <input type="hidden" name="offer_id" value="<?php echo $book['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="back" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#books'); ?>">
                            <button class="card-cart-button" type="submit" <?php echo intval($book['quantity']) <= 0 ? 'disabled' : ''; ?>>
                                <?php echo intval($book['quantity']) <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                            </button>
                        </form>
                        <a class="details-button" href="/lustro_books/Books/<?php echo $book['id']; ?>">View Details</a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="empty-box">
            <h3>No books found</h3>
            <p>Try searching with another word.</p>
            <a href="/lustro_books/">Back to all books</a>
        </div>
    <?php } ?>
</section>

<?php include 'footer.php'; ?>
