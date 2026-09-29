<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

function order_email_message(array $order, string $event): array
{
    $orderId = (int) $order['id'];
    $greeting = 'Hello ' . $order['full_name'] . ",\n\n";

    if ($event === 'confirmed') {
        $subject = "Order #$orderId confirmed | LUSTRO BOOKS";
        $body = "Your order #$orderId has been confirmed.\n";
        $body .= 'Total: ' . number_format((float) $order['total_price'], 2) . " SAR\n\n";
        $body .= "You can cancel your order only within 24 hours of confirmation, while it is still pending.\n";
        $body .= "To cancel, sign in to LUSTRO BOOKS, open My Orders, and select Cancel Order.\n";
    } elseif ($event === 'customer_cancelled') {
        $subject = "Order #$orderId cancelled | LUSTRO BOOKS";
        $body = "Your order #$orderId has been cancelled at your request.\n";
        $body .= "The cancellation applies to the entire order.\n";
    } elseif ($event === 'seller_cancelled') {
        $subject = "Order #$orderId cancelled | LUSTRO BOOKS";
        $body = "We are sorry, your order #$orderId has been cancelled because a seller could not provide one of the books you ordered.\n";
        $body .= "The entire order has been cancelled, including the other books in it.\n";
    } else {
        throw new InvalidArgumentException('Unknown order email event.');
    }

    return ['subject' => $subject, 'body' => $greeting . $body . "\nLUSTRO BOOKS"];
}


function send_order_email(mysqli $conn, int $orderId, string $event): bool
{
    try {
        $stmt = $conn->prepare('SELECT id, full_name, email, total_price FROM orders WHERE id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $message = order_email_message($order, $event);
        $config = require __DIR__ . '/mail_config.php';
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
        $mail->addAddress($order['email']);
        $mail->Subject = $message['subject'];
        $mail->Body = $message['body'];
        return $mail->send();
    } catch (Throwable $error) {
        error_log("Order email failed: order #$orderId, event $event.");
        return false;
    }
}
