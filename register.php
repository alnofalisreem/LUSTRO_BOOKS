<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/');
    exit;
}
$pageTitle = 'Create Account | LUSTRO BOOKS';
require_once 'email_verification.php';
include 'header.php';
$errors = $_SESSION['register_errors'] ?? [];
$old = $_SESSION['register_old'] ?? [];
// The data is copied above, so clear the temporary session values
unset($_SESSION['register_errors'], $_SESSION['register_old']);
?>
<main class="auth-page">
    <section class="auth-card wide">
        <div class="auth-intro">
            <p class="eyebrow">JOIN LUSTRO BOOKS</p>
            <h1>Create your account</h1>
        </div>

        <?php if ($errors): ?>
            <div class="form-alert error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
        <?php endif; ?>

        <form class="auth-form register-grid" action="process_register.php" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(verification_csrf()) ?>">
            <div>
                <label for="firstName">First name</label>
                <input id="firstName" type="text" name="firstName" value="<?= htmlspecialchars($old['firstName'] ?? '') ?>" required>
            </div>
            <div>
                <label for="lastName">Last name</label>
                <input id="lastName" type="text" name="lastName" value="<?= htmlspecialchars($old['lastName'] ?? '') ?>" required>
            </div>
            <div class="full-field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
            </div>
            <div class="full-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" minlength="6" pattern="(?=.*[!@#$%&amp;*]).{6,}" title="Use at least 6 characters and one symbol: ! @ # $ % &amp; *" required>
                <small>Use at least 6 characters and one symbol: ! @ # $ % &amp; *</small>
            </div>
            <div class="full-field">
                <label for="address">Short  Address</label>
                <input id="address" type="text" name="address" value="<?= htmlspecialchars($old['address'] ?? '') ?>" placeholder="e.g. ABCD1234" required>
            </div>
            <div class="full-field">
                <label for="mobile">Mobile</label>
                <input id="mobile" type="tel" name="mobile" value="<?= htmlspecialchars($old['mobile'] ?? '') ?>" pattern="[0-9]{10}" placeholder="05xxxxxxxx" required>
            </div>
            <div class="full-field seller-choice">
                <input id="canSell" type="checkbox" name="can_sell" value="1" <?= !empty($old['can_sell']) ? 'checked' : '' ?>>
                <div class="seller-choice-content">
                    <label for="canSell">
                        <strong>I want to sell books on LUSTRO BOOKS</strong>
                        <small>Select this option to publish books and manage your listings.</small>
                    </label>
                    <a class="selling-policy-link" href="/lustro_books/Book-Selling-Policy" target="_blank" rel="noopener" aria-label="Book Selling Policy (opens in a new tab)">Book Selling Policy</a>
                </div>
            </div>
            <div class="full-field seller-choice terms-choice">
                <input id="agreePolicies" type="checkbox" name="agree_policies" value="1" <?= !empty($old['agree_policies']) ? 'checked' : '' ?> required>
                <div class="seller-choice-content">
                    <label for="agreePolicies">
                        <strong>I agree to the Terms &amp; Conditions and Privacy Policy</strong>
                        <small>You must accept the store policies to create an account.</small>
                    </label>
                    <span class="selling-policy-link"><a href="/lustro_books/Terms-and-Conditions" target="_blank" rel="noopener" aria-label="Terms &amp; Conditions (opens in a new tab)">Terms &amp; Conditions</a> and <a href="/lustro_books/Privacy-Policy" target="_blank" rel="noopener" aria-label="Privacy Policy (opens in a new tab)">Privacy Policy</a></span>
                </div>
            </div>
            <div class="full-field">
                <button type="submit">Create account</button>
                <p class="form-switch">Already registered? <a href="/lustro_books/Sign-in">Sign in</a></p>
            </div>
        </form>
    </section>
</main>
<?php include 'footer.php'; ?>


