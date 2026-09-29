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
$permission = $conn->prepare('SELECT can_sell FROM users WHERE id = ? LIMIT 1');
$permission->bind_param('i', $userId);
$permission->execute();
$user = $permission->get_result()->fetch_assoc();
$permission->close();

if (!$user || intval($user['can_sell']) !== 1) {
    $_SESSION['flash'] = 'Your account is not enabled for selling books.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /lustro_books/');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lustro_books/Publish-a-Book');
    exit;
}

$requiredFields = ['title', 'description', 'org_price', 'discount', 'cat_id', 'quantity', 'book_condition'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || !is_string($_POST[$field]) || trim($_POST[$field]) === '') {
        $_SESSION['offer_errors'] = ['Please fill in all fields before publishing your book.'];
        $_SESSION['offer_old'] = array_intersect_key(array_filter($_POST, 'is_string'), array_flip($requiredFields));
        header('Location: /lustro_books/Publish-a-Book');
        exit;
    }
}
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$orgPrice = filter_var($_POST['org_price'] ?? null, FILTER_VALIDATE_FLOAT);
$discount = filter_var($_POST['discount'] ?? null, FILTER_VALIDATE_INT);
$catId = filter_var($_POST['cat_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
$bookCondition = $_POST['book_condition'] ?? '';
$errors = [];
if (!in_array($bookCondition, ['new', 'used'], true)) $errors[] = 'Please select New or Used for the book condition.';

if ($title === '' || $description === '') $errors[] = 'Please enter the title and description.';
if ($orgPrice === false || !is_finite($orgPrice) || $orgPrice < 25 || $orgPrice > 60) $errors[] = 'Price must be between SAR 25 and SAR 60 before discount.';
if ($discount === false || $discount < 0 || $discount > 10) $errors[] = 'Discount must be between 0% and 10%.';
if ($catId === false || $catId <= 0) {
    $errors[] = 'Select a valid category.';
} else {
    // Check that the category exists in the database
    $categoryCheck = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $categoryCheck->bind_param('i', $catId);
    $categoryCheck->execute();
    if ($categoryCheck->get_result()->num_rows === 0) {
        $errors[] = 'Select a valid category.';
    }
    $categoryCheck->close();
}
if ($quantity === false || $quantity < 1 || $quantity > 5) $errors[] = 'Quantity must be between 1 and 5.';

$imagePath = '';
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please select a book image.';
} else {
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    // Check the file type instead of trusting its extension
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
    if (!isset($allowedTypes[$mime])) {
        $errors[] = 'Allowed image formats: JPG, PNG, GIF.';
    } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The image must be smaller than 5 MB.';
    } else {
        // Use a random image name to avoid name clashes
        $imagePath = 'uploads/' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mime];
    }
}

if ($errors) {
    $_SESSION['offer_errors'] = $errors;
    $_SESSION['offer_old'] = $_POST;
    header('Location: /lustro_books/Publish-a-Book');
    exit;
}
if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/' . $imagePath)) {
    $_SESSION['offer_errors'] = ['Unable to save the image. Please try again.'];
    header('Location: /lustro_books/Publish-a-Book');
    exit;
}

$stmt = $conn->prepare('INSERT INTO books (title, image, description, org_price, discount, cat_id, user_id, quantity, book_condition, creation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('sssdiiiis', $title, $imagePath, $description, $orgPrice, $discount, $catId, $userId, $quantity, $bookCondition);
if (!$stmt->execute()) {
    if (is_file(__DIR__ . '/' . $imagePath)) unlink(__DIR__ . '/' . $imagePath);
    $_SESSION['offer_errors'] = ['Unable to publish the book. Please try again.'];
    header('Location: /lustro_books/Publish-a-Book');
    exit;
}

unset($_SESSION['offer_old']);
$_SESSION['flash'] = 'Your book has been published successfully.';
$_SESSION['flash_type'] = 'success';
header('Location: /lustro_books/My-Books');
exit;
