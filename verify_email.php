<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';
require_once 'email_verification.php';
if (isset($_SESSION['user_id'])) {
    header('Location: /lustro_books/');
    exit;
}
if (empty($_SESSION['pending_registration'])) {
    header('Location: /lustro_books/Create-Account');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (!verification_valid_csrf()) {
  $_SESSION['verification_error'] = 'Your form expired. Please try again.';
 } elseif (($_POST['action'] ?? '') === 'resend') {
  try {
   send_verification_code($conn);
   $_SESSION['verification_notice'] = 'A new code has been sent. Only the newest code will work.';
  } catch (DomainException $e) {
   $_SESSION['verification_error'] = $e->getMessage();
  } catch (Throwable $e) {
   error_log('Email verification resend failed.');
   $_SESSION['verification_error'] = 'Email verification is temporarily unavailable. Please try again later.';
  }
 } else {
  // The & makes changes here update the session data too
  $pending = &$_SESSION['pending_registration'];
  $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
  if (!$pending['sent_at']) {
   $_SESSION['verification_error'] = 'Please request a code first.';
  } elseif ($pending['attempts'] >= 3) {
   $_SESSION['verification_error'] = 'Too many incorrect attempts. Please request a new code.';
  } elseif ($pending['expires_at'] <= time()) {
   $_SESSION['verification_error'] = 'This code has expired. Please request a new code.';
  } elseif (!verification_code_valid($pending, $code, time())) {
   $pending['attempts']++;
   $_SESSION['verification_error'] = $pending['attempts'] >= 3
    ? 'Too many incorrect attempts. Please request a new code.'
    : 'Incorrect code. Please check your email and try again.';
  } else {
   $account = $pending['account'];
   try {
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $account['email']); $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
     unset($_SESSION['pending_registration']);
     $_SESSION['login_error'] = 'This email is already registered. Please sign in.';
    } else {
     $stmt = $conn->prepare('INSERT INTO users (firstName,lastName,email,password,address,mobile,can_sell,creation_date) VALUES (?,?,?,?,?,?,?,CURDATE())');
     $stmt->bind_param('ssssssi', $account['firstName'], $account['lastName'], $account['email'], $account['hashedPassword'], $account['address'], $account['mobile'], $account['canSell']);
     if (!$stmt->execute()) {
      throw new mysqli_sql_exception($stmt->error, $stmt->errno);
     }
     $id = $stmt->insert_id;
     session_regenerate_id(true);
     $_SESSION['user_id'] = $id;
     $_SESSION['user_name'] = trim($account['firstName'].' '.$account['lastName']);
     $_SESSION['user_email'] = $account['email'];
     $_SESSION['can_sell'] = $account['canSell'];
     unset($_SESSION['pending_registration'], $_SESSION['register_old'], $_SESSION['verification_csrf'], $_SESSION['verification_notice'], $_SESSION['verification_error']);
     $_SESSION['flash'] = 'Your email has been verified and your account is ready.';
     $_SESSION['flash_type'] = 'success';
    }
   } catch (Throwable $e) {
    // 1062 means the email was already used when saving
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
     unset($_SESSION['pending_registration']);
     $_SESSION['login_error'] = 'This email is already registered. Please sign in.';
    } else {
     error_log('Could not create the account.');
     $_SESSION['verification_error'] = "We couldn't create your account. Please try again.";
    }
   }
   if (isset($_SESSION['user_id'])) {
       header('Location: /lustro_books/');
       exit;
   }
   if (empty($_SESSION['pending_registration'])) {
       header('Location: /lustro_books/Sign-in');
       exit;
   }
  }
 }
 header('Location: /lustro_books/Verify-Email');
 exit;
}
$pending = $_SESSION['pending_registration'];
$error = $_SESSION['verification_error'] ?? '';
$notice = $_SESSION['verification_notice'] ?? '';
unset($_SESSION['verification_error'], $_SESSION['verification_notice']);
$pageTitle = 'Verify Email | LUSTRO BOOKS';
include 'header.php';
?>
<main class="auth-page verification-page">
 <section class="auth-card verification-card" aria-labelledby="verification-title">
  <div class="verification-icon" aria-hidden="true">
   <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
  </div>
  <div class="auth-intro">
   <h1 id="verification-title">Verify your email</h1>
   <p><?= $pending['sent_at'] ? 'Enter the verification code sent to' : 'Request a verification code for' ?></p>
   <div class="verification-address"><strong><?= htmlspecialchars($pending['account']['email']) ?></strong><a href="/lustro_books/Create-Account">Change</a></div>
  </div>
  <?php if ($error): ?><div class="form-alert error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="verification-notice" role="status"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <form id="verification-form" class="auth-form verification-form" action="/lustro_books/Verify-Email" method="post"
   data-remaining="<?= max(0, $pending['expires_at'] - time()) ?>"
   data-resend="<?= max(0, $pending['sent_at'] + 60 - time()) ?>"
   data-locked="<?= $pending['attempts'] >= 3 ? '1' : '0' ?>">
   <input type="hidden" name="csrf" value="<?= htmlspecialchars(verification_csrf()) ?>">
   <input type="hidden" name="action" value="verify">
   <label class="verification-label" for="code">Verification code</label>
   <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="verification-timer">
   <div class="verification-digits" role="group" aria-label="6-digit verification code" hidden>
    <?php for ($digit = 1; $digit <= 6; $digit++): ?>
     <input type="text" class="verification-digit" inputmode="numeric" autocomplete="<?= $digit === 1 ? 'one-time-code' : 'off' ?>" aria-label="Digit <?= $digit ?> of 6" aria-describedby="verification-timer" pattern="[0-9]" <?= $error ? 'aria-invalid="true"' : '' ?>>
    <?php endfor; ?>
   </div>
   <p id="verification-timer" class="verification-timer">Code expires in 2 minutes</p>
   <button id="verification-submit" type="submit">Verify email</button>
  </form>
  <form id="resend-form" class="verification-resend" action="/lustro_books/Verify-Email" method="post">
   <input type="hidden" name="csrf" value="<?= htmlspecialchars(verification_csrf()) ?>">
   <input type="hidden" name="action" value="resend">
   <span>Didn't receive the code?</span>
   <button id="resend-code" type="submit">Resend code</button>
  </form>

 </section>
</main>
<script src="email_verification.js" defer></script>
<?php include 'footer.php'; ?>

