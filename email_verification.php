<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';


function verification_csrf(): string
{
    if (empty($_SESSION['verification_csrf'])) {
        // Create a session token for the form, separate from the email code
        $_SESSION['verification_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['verification_csrf'];
}

function verification_valid_csrf(): bool
{
    if (!is_string($_POST['csrf'] ?? null)) {
        return false;
    }
    return hash_equals(verification_csrf(), $_POST['csrf']);
}

function verification_code_valid(array $pending, string $code, int $now): bool
{
    if ($pending['attempts'] >= 3 || $now >= $pending['expires_at']) {
        return false;
    }
    if (!preg_match('/^[0-9]{6}$/D', $code)) {
        return false;
    }
    return password_verify($code, $pending['code_hash']);
}

function send_verification_code(mysqli $conn): void
{
    $email = $_SESSION['pending_registration']['account']['email'];
    $config = require __DIR__ . '/mail_config.php';

    if (!$config['username'] || !$config['password'] || !$config['from_email']) {
        throw new DomainException('Email delivery is not configured yet. Please try again later.');
    }

    
    // Use a lock for this email so two requests cannot pass the send limit together
    $emailKey = hash('sha256', $email);
    $lockName = 'verify:' . substr($emailKey, 0, 56);
    $stmt = $conn->prepare('SELECT GET_LOCK(?, 5)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $lockResult = $stmt->get_result()->fetch_row();
    if ((int) $lockResult[0] !== 1) {
        throw new DomainException('Please wait a moment and try again.');
    }

    try {
        $now = time();
        $stmt = $conn->prepare('SELECT last_sent, window_start, send_count FROM email_verification_limits WHERE email_key = ?');
        $stmt->bind_param('s', $emailKey);
        $stmt->execute();
        $limit = $stmt->get_result()->fetch_assoc();

        if ($limit && $now - $limit['last_sent'] < 60) {
            throw new DomainException('Please wait a minute before trying again.');
        }

        // Count requests within one hour, then start a new count
        $windowStart = $now;
        $sendCount = 1;
        if ($limit && $now - $limit['window_start'] < 3600) {
            $windowStart = (int) $limit['window_start'];
            $sendCount = (int) $limit['send_count'] + 1;
        }
        if ($sendCount > 3) {
            throw new DomainException('Too many code requests. Please try again in one hour.');
        }

        
        $stmt = $conn->prepare('INSERT INTO email_verification_limits (email_key, last_sent, window_start, send_count)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_sent = VALUES(last_sent), window_start = VALUES(window_start), send_count = VALUES(send_count)');
        $stmt->bind_param('siii', $emailKey, $now, $windowStart, $sendCount);
        $stmt->execute();

        $code = (string) random_int(100000, 999999);

        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($email);
        $mail->Subject = 'Your LUSTRO BOOKS verification code';
        $mail->Body = "Your verification code is: $code\n\nThis code expires in 2 minutes.\nDo not share this code with anyone.\nIf you did not request an account, ignore this email.\n\nLUSTRO BOOKS";

        try {
            $mail->send();
        } catch (Throwable $e) {
            error_log('Verification email delivery failed.');
            throw new DomainException('We could not send your code. Please try again in a minute.');
        }

        
        // Save the new code hash and reset the time and attempts
        $_SESSION['pending_registration']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['pending_registration']['expires_at'] = time() + 120;
        $_SESSION['pending_registration']['attempts'] = 0;
        $_SESSION['pending_registration']['sent_at'] = time();
    } finally {
        // Release the lock even if sending fails
        $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->bind_param('s', $lockName);
        $stmt->execute();
    }
}
