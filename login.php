<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/');
    exit;
}
$pageTitle = 'Sign In | LUSTRO BOOKS';
include 'header.php';
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<main class="auth-page">
    <section class="auth-card">
        <div class="auth-intro">
            <p class="eyebrow">WELCOME BACK</p>
            <h1>Sign in to your account</h1>
        </div>
        <?php if ($error): ?><div class="form-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form class="auth-form" action="process_login.php" method="post">
            <label for="email">Email address</label>
            <input id="email" type="email" name="email" required autocomplete="email">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <button type="submit">Sign in</button>
            <p class="form-switch">New to LUSTRO BOOKS? <a href="/lustro_books/Create-Account">Create an account</a></p>
        </form>
    </section>
</main>
<?php include 'footer.php'; ?>
