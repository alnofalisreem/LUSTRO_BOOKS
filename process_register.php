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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lustro_books/Create-Account');
    exit;
}

$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$address = trim($_POST['address'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$canSell = isset($_POST['can_sell']) ? 1 : 0;
$agreedToPolicies = isset($_POST['agree_policies']) && $_POST['agree_policies'] === '1';

// Keep the form data without the password in case of an error
$_SESSION['register_old'] = compact('firstName', 'lastName', 'email', 'address', 'mobile');
$_SESSION['register_old']['can_sell'] = $canSell;
$_SESSION['register_old']['agree_policies'] = $agreedToPolicies ? 1 : 0;
$errors = [];
if (!verification_valid_csrf()) $errors[] = 'Your form expired. Please try again.';

if ($firstName === '' || $lastName === '') $errors[] = 'First and last name are required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
if (strlen($password) < 6) $errors[] = 'Password must contain at least 6 characters.';
if (!preg_match('/[!@#$%&*]/', $password)) $errors[] = 'Add at least one password symbol: ! @ # $ % & *';
if ($address === '') $errors[] = 'Address is required.';
if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = 'Mobile number must contain 10 digits.';
if (!$agreedToPolicies) $errors[] = 'You must agree to the Terms & Conditions and Privacy Policy.';

if (!$errors) {
    $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $errors[] = 'An account with this email already exists.';
    }
    $check->close();
}

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    header('Location: /lustro_books/Create-Account');
    exit;
}


$account = [
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'hashedPassword' => password_hash($password, PASSWORD_DEFAULT),
    'address' => $address,
    'mobile' => $mobile,
    'canSell' => $canSell,
];

session_regenerate_id(true);
// Keep the code state for the same email; start over if the email changes
$previous = $_SESSION['pending_registration'] ?? null;
if (!$previous || $previous['account']['email'] !== $email) {
    $_SESSION['pending_registration'] = [
        'account' => $account,
        'code_hash' => '',
        'expires_at' => 0,
        'attempts' => 0,
        'sent_at' => 0,
    ];
}
$_SESSION['pending_registration']['account'] = $account;

try {
    // Do not send another code just because the form was submitted again
    if (!$_SESSION['pending_registration']['sent_at']) {
        send_verification_code($conn);
        $_SESSION['verification_notice'] = 'We sent a verification code to your email.';
    }
} catch (DomainException $e) {
    $_SESSION['verification_error'] = $e->getMessage();
} catch (Throwable $e) {
    error_log('Could not send the verification code.');
    $_SESSION['verification_error'] = 'Email verification is temporarily unavailable. Please try again later.';
}

header('Location: /lustro_books/Verify-Email');
exit;
