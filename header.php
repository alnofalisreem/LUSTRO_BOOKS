<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

$logged = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'My Account';
$favoriteCount = 0;
$cartCount = 0;
$canSell = false;

if ($logged) {
    $userId = intval($_SESSION['user_id']);
    $userResult = mysqli_query($conn, "SELECT can_sell FROM users WHERE id = $userId LIMIT 1");
    if ($userResult && $userRow = mysqli_fetch_assoc($userResult)) {
        $canSell = intval($userRow['can_sell']) === 1;
        $_SESSION['can_sell'] = $canSell ? 1 : 0;
    }

    $favResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM favorites WHERE user_id = $userId");
    if ($favResult) {
        $favoriteCount = intval(mysqli_fetch_assoc($favResult)['total']);
    }

    $cartResult = mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) AS total FROM cart_items WHERE user_id = $userId");
    if ($cartResult) {
        $cartCount = intval(mysqli_fetch_assoc($cartResult)['total']);
    }
}

$searchValue = isset($_GET['q']) ? trim($_GET['q']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <base href="/lustro_books/">
    <meta name="viewport" content="width=1280">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'LUSTRO BOOKS'; ?></title>
    <link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body>
<header>
    <a class="logo" href="/lustro_books/">
        <img src="icon/logo-new.svg" alt="LUSTRO BOOKS">
    </a>

    <div class="search-box">
        <form action="/lustro_books/" method="GET">
            <input type="text" name="q" value="<?php echo htmlspecialchars($searchValue); ?>" placeholder="Search by book title or description...">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="header-links">
        <?php if ($logged) { ?>
            <div class="account-box">
                <button type="button" class="account-trigger" onclick="showAccount()" aria-expanded="false" aria-controls="accountList">
                    <img src="icon/account.svg" alt="Account">
                    <span><?php echo htmlspecialchars($userName); ?></span>
                </button>
                <div class="account-list" id="accountList">
                    <div class="account-summary">
                        <div>
                            <strong><?php echo htmlspecialchars($userName); ?></strong>
                            <small><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
                        </div>
                    </div>
                    <a class="account-menu-link" href="/lustro_books/My-Orders"><img src="icon/cart.svg" alt="">My Orders</a>
                    <?php if ($canSell) { ?>
                        <a class="account-menu-link" href="/lustro_books/My-Books"><img src="icon/book.svg" alt="">My Books</a>
                    <?php } ?>
                    <a class="account-logout" href="/lustro_books/Log-out"><img src="icon/logout.svg" alt="">Log out</a>
                </div>
            </div>
        <?php } else { ?>
            <a href="/lustro_books/Sign-in"><img src="icon/login.svg" alt="Sign in"><span>Sign in</span></a>
        <?php } ?>

        <a href="/lustro_books/My-Favorites">
            <img src="icon/heart.svg" alt="Favorites">
            <span>Favorites</span>
            <span class="cart-number"><?php echo $favoriteCount; ?></span>
        </a>

        <a href="/lustro_books/My-Cart">
            <img src="icon/cart.svg" alt="Cart">
            <span>Cart</span>
            <span class="cart-number"><?php echo $cartCount; ?></span>
        </a>
    </div>
</header>

<?php include 'menu.php'; ?>

<?php if (!empty($_SESSION['flash'])) { ?>
    <div class="message <?php echo htmlspecialchars($_SESSION['flash_type'] ?? ''); ?>">
        <?php echo htmlspecialchars($_SESSION['flash']); ?>
    </div>
    <?php unset($_SESSION['flash'], $_SESSION['flash_type']); ?>
<?php } ?>

<script>
function showAccount() {
    var list = document.getElementById('accountList');
    var button = document.querySelector('.account-trigger');
    var isOpen = list.classList.toggle('open');
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

document.addEventListener('click', function (event) {
    var accountBox = document.querySelector('.account-box');
    if (!accountBox || accountBox.contains(event.target)) return;
    document.getElementById('accountList').classList.remove('open');
    document.querySelector('.account-trigger').setAttribute('aria-expanded', 'false');
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var list = document.getElementById('accountList');
    if (!list) return;
    list.classList.remove('open');
    document.querySelector('.account-trigger').setAttribute('aria-expanded', 'false');
});
</script>
