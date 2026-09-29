<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please sign in to publish a book.';
    $_SESSION['flash_type'] = 'error';
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

$errors = $_SESSION['offer_errors'] ?? [];
$old = $_SESSION['offer_old'] ?? [];
unset($_SESSION['offer_errors'], $_SESSION['offer_old']);
$categories = mysqli_query($conn, 'SELECT id, title FROM categories ORDER BY id');
$pageTitle = 'Publish a Book | LUSTRO BOOKS';
include 'header.php';
?>
<main class="auth-page">
    <section class="auth-card wide">
        <div class="auth-intro">
            <p class="eyebrow">SELL WITH LUSTRO BOOKS</p>
            <h1>Publish a book</h1>
            <p>Please make sure to fill in all fields with accurate information.</p>
        </div>
        <?php if ($errors) { ?><div class="form-alert error"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div><?php } ?>
        <form class="auth-form offer-form" action="save_book.php" method="post" enctype="multipart/form-data">
            <div class="offer-field offer-field-wide">
                <label for="title">Book title</label>
                <input id="title" type="text" name="title" maxlength="100" placeholder="Enter the book title" value="<?php echo htmlspecialchars($old['title'] ?? ''); ?>" required>
            </div>

            <div class="offer-field offer-field-wide image-upload-field">
                <label for="image">Book cover</label>
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/gif" required>
                <small>JPG, PNG, GIF — maximum size 5 MB.</small>
            </div>

            <div class="offer-field offer-field-wide">
                <label for="description">Description</label>
                <textarea id="description" rows="5" name="description" placeholder="Tell us about your book and what it covers." required><?php echo htmlspecialchars($old['description'] ?? ''); ?></textarea>
            </div>

            <div class="offer-field">
                <div class="offer-price-label"><label for="orgPrice">Price</label><small id="price-help">must be between 25 - 60 SAR</small></div>
                <input id="orgPrice" type="number" name="org_price" min="25" max="60" step="0.01" placeholder="25.00" aria-describedby="price-help" value="<?php echo htmlspecialchars($old['org_price'] ?? '25'); ?>" required>
            </div>
            <div class="offer-field">
                <div class="offer-price-label"><label for="discount">Discount</label><small id="discount-help">must be between 0 - 10 %</small></div>
                <input id="discount" type="number" name="discount" min="0" max="10" aria-describedby="discount-help" value="<?php echo htmlspecialchars($old['discount'] ?? '0'); ?>" required>
            </div>
            <div class="offer-field">
                <label for="category">Category</label>
                <select id="category" name="cat_id" required>
                    <option value="" disabled <?php echo empty($old['cat_id']) ? 'selected' : ''; ?>>Select</option>
                    <?php while ($category = mysqli_fetch_assoc($categories)) { ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo intval($old['cat_id'] ?? 0) === intval($category['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['title']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="offer-field">
                <div class="offer-price-label"><label for="quantity">Available quantity</label><small id="quantity-help">must be between 1 - 5</small></div>
                <input id="quantity" type="number" min="1" max="5" name="quantity" aria-describedby="quantity-help" value="<?php echo htmlspecialchars($old['quantity'] ?? '1'); ?>" required>
            </div>

            <div class="offer-field offer-field-wide">
                <label for="bookCondition">Is your book new or used?</label>
                <select id="bookCondition" name="book_condition" required>
                    <option value="" disabled <?php echo empty($old['book_condition']) ? 'selected' : ''; ?>>Select</option>
                    <option value="new" <?php echo ($old['book_condition'] ?? '') === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="used" <?php echo ($old['book_condition'] ?? '') === 'used' ? 'selected' : ''; ?>>Used</option>
                </select>
            </div>
            <div class="offer-actions offer-field-wide">
                <a class="form-back-link" href="/lustro_books/My-Books">Cancel</a>
                <button type="submit">Publish book</button>
            </div>
        </form>
    </section>
</main>
<?php include 'footer.php'; ?>
