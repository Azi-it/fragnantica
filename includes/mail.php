<?php
// email: one send_mail() function plus the store's email templates
// loaded by includes/functions.php; uses the settings in includes/settings.php

require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/receipt.php';

// send (or, with MAIL_DRIVER 'log', save) one HTML email. returns true on success.
// $attachments: list of array('name' => ..., 'type' => ..., 'data' => raw bytes)
// a failed email never stops the page: callers carry on either way.
function send_mail($to, $subject, $contentHtml, $attachments = array())
{
    // a line break in a header would let someone add their own headers, so remove them
    $to = str_replace(array("\r", "\n"), '', (string)$to);
    $subject = str_replace(array("\r", "\n"), ' ', (string)$subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $body = email_layout($subject, $contentHtml);

    // 'smtp': send through Mailtrap (or another SMTP server)
    if (MAIL_DRIVER === 'smtp' && SMTP_USERNAME !== '') {
        try {
            smtp_send($to, mime_message($to, $subject, $body, $attachments));
            return true;
        } catch (RuntimeException $e) {
            error_log("send_mail: could not send '{$subject}' to {$to}: " . $e->getMessage());
            // keep a copy so the email is not lost, then report the failure
            save_mail_copy($to, $subject, $body, $attachments, 'NOT SENT: ' . $e->getMessage());
            return false;
        }
    }

    // 'log' (or 'smtp' without a Mailtrap login): save the email instead of sending it
    $why = MAIL_DRIVER === 'smtp' ? 'no SMTP username in includes/mail_config.php' : "MAIL_DRIVER is 'log'";
    return save_mail_copy($to, $subject, $body, $attachments, "Saved, not sent ({$why})");
}

// save an email as a web page in storage/mail/, with its attachments next to it
function save_mail_copy($to, $subject, $body, $attachments, $reason)
{
    $dir = __DIR__ . '/../storage/mail';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        error_log("send_mail: cannot create {$dir}");
        return false;
    }
    $base = date('Ymd-His') . '-' . bin2hex(random_bytes(3));

    $files = array();
    foreach ($attachments as $file) {
        $name = $base . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
        if (file_put_contents($dir . '/' . $name, $file['data']) !== false) {
            $files[] = $name;
        }
    }

    $banner = "<div style=\"font-family:Arial,sans-serif;font-size:13px;background:#FFF3CD;border:1px solid #E0C97A;padding:10px;margin-bottom:16px;\">"
        . "<strong>" . e($reason) . "</strong><br>"
        . "<strong>To:</strong> " . e($to) . "<br>"
        . "<strong>Subject:</strong> " . e($subject) . "<br>"
        . "<strong>Date:</strong> " . date('M j, Y, g:i:s a');
    if ($files) {
        $links = array();
        foreach ($files as $name) {
            $links[] = "<a href=\"" . e($name) . "\">" . e($name) . "</a>";
        }
        $banner .= "<br><strong>Attachments:</strong> " . implode(', ', $links);
    }
    $banner .= "</div>";

    $page = "<!doctype html><html><head><meta charset=\"utf-8\"><title>" . e($subject) . "</title></head>"
        . "<body style=\"background:#F4F4F4;padding:20px;\">{$banner}{$body}</body></html>";

    return file_put_contents($dir . '/' . $base . '.html', $page) !== false;
}

// the frame around every email: store name on top, a small footer below
function email_layout($title, $contentHtml)
{
    $store = e(MAIL_FROM_NAME);
    $year = date('Y');
    return "<div style=\"max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #EAEAEA;font-family:Georgia,'Times New Roman',serif;color:#3D3D3D;\">"
        . "<div style=\"background:#8B1E3F;color:#FFFFFF;padding:16px 20px;font-family:'Trebuchet MS',Arial,sans-serif;font-size:18px;\">{$store}</div>"
        . "<div style=\"padding:20px;\"><h2 style=\"font-family:'Trebuchet MS',Arial,sans-serif;font-size:20px;margin:0 0 14px;\">" . e($title) . "</h2>"
        . $contentHtml
        . "</div>"
        . "<div style=\"padding:12px 20px;font-size:12px;color:#888888;border-top:1px solid #EAEAEA;\">&copy; {$year} {$store}. You are receiving this because you have an account with us.</div>"
        . "</div>";
}

function email_button($url, $label)
{
    return "<p style=\"margin:20px 0;\"><a href=\"" . e($url) . "\" style=\"background:#8B1E3F;color:#FFFFFF;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;\">" . e($label) . "</a></p>";
}

// the list of products in an order with their subtotals and the grand total, as an email-friendly table
// (read from the order_details_view and order_summary_view views)
function email_order_table($conn, $order_id)
{
    $sql = "SELECT product_name, brand, quantity, price, subtotal FROM order_details_view WHERE order_id = ? ORDER BY order_item_id";
    $items = mysqli_fetch_all(mysqli_execute_query($conn, $sql, [$order_id]), MYSQLI_ASSOC);
    $sql = "SELECT grand_total FROM order_summary_view WHERE order_id = ? LIMIT 1";
    $summary = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$order_id]));
    $grandTotal = $summary ? $summary['grand_total'] : 0;

    $cell = "padding:6px 8px;border-bottom:1px solid #EAEAEA;";
    $rows = '';
    foreach ($items as $item) {
        $rows .= "<tr><td style=\"{$cell}\">" . e($item['product_name']) . "<br><small style=\"color:#888888;\">" . e($item['brand']) . "</small></td>"
            . "<td style=\"{$cell}text-align:right;\">" . peso($item['price']) . "</td>"
            . "<td style=\"{$cell}text-align:center;\">" . (int)$item['quantity'] . "</td>"
            . "<td style=\"{$cell}text-align:right;\">" . peso($item['subtotal']) . "</td></tr>";
    }
    return "<table style=\"width:100%;border-collapse:collapse;font-size:14px;\">"
        . "<tr style=\"background:#F5F5F5;\"><th style=\"{$cell}text-align:left;\">Product</th><th style=\"{$cell}text-align:right;\">Price</th>"
        . "<th style=\"{$cell}\">Qty</th><th style=\"{$cell}text-align:right;\">Subtotal</th></tr>"
        . $rows
        . "<tr><th colspan=\"3\" style=\"padding:8px;text-align:right;\">Grand Total</th>"
        . "<th style=\"padding:8px;text-align:right;color:#8B1E3F;\">" . peso($grandTotal) . "</th></tr>"
        . "</table>";
}

// fetch what every order email needs: the order and who it belongs to
function email_order_info($conn, $order_id)
{
    $sql = "SELECT order_id, status, grand_total, payment_method, payment_status, shipping_address,
                   customer_name AS name, customer_email AS email
            FROM order_summary_view
            WHERE order_id = ? LIMIT 1";
    return mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$order_id]));
}

// "we received your order", with the receipt attached
function notify_order_placed($conn, $order_id)
{
    $order = email_order_info($conn, $order_id);
    if (!$order) {
        return false;
    }
    $link = APP_URL . "/cart/order_confirmation.php?id=" . (int)$order_id;
    $due = $order['payment_method'] === 'cod'
        ? "Please prepare <strong>" . peso($order['grand_total']) . "</strong> in cash for when your order arrives."
        : "Payment: " . e(payment_label($order['payment_method'])) . ".";

    $html = "<p>Hi " . e($order['name']) . ",</p>"
        . "<p>Thank you for your order! We have received order <strong>#" . (int)$order_id . "</strong> and will start preparing it soon.</p>"
        . email_order_table($conn, $order_id)
        . "<p><strong>Payment:</strong> " . e(payment_label($order['payment_method'])) . "<br>{$due}</p>"
        . "<p><strong>Deliver to:</strong><br>" . e($order['shipping_address']) . "</p>"
        . "<p>You can cancel this order while it is still pending. Your receipt is attached as a PDF.</p>"
        . email_button($link, 'View your order');

    return send_mail($order['email'], "Order #" . (int)$order_id . " received", $html, order_receipt_attachment($conn, $order_id));
}

// "your order is now processing / shipped / completed / cancelled":
// the new status, the products with their subtotals and the grand total, and the PDF receipt
function notify_order_status($conn, $order_id)
{
    $order = email_order_info($conn, $order_id);
    if (!$order || $order['status'] === 'pending') {
        return false;
    }

    // the most recent note, e.g. a tracking number
    $sql = "SELECT note FROM order_status_history WHERE order_id = ? ORDER BY changed_at DESC, history_id DESC LIMIT 1";
    $row = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$order_id]));
    $note = $row ? (string)$row['note'] : '';

    $id = (int)$order_id;
    $messages = array(
        'processing' => array("Your order #{$id} is being prepared", "Good news: we are now preparing your order."),
        'shipped'    => array("Your order #{$id} is on its way", "Your order has been shipped and is on its way to you."),
        'completed'  => array("Order #{$id} completed", "Your order has been delivered. Thank you for shopping with us! You can now review the products you bought."),
        'cancelled'  => array("Order #{$id} was cancelled", "Your order has been cancelled. Nothing will be delivered or charged."),
    );
    if (!isset($messages[$order['status']])) {
        return false;
    }
    list($subject, $line) = $messages[$order['status']];

    $html = "<p>Hi " . e($order['name']) . ",</p><p>{$line}</p>"
        . "<p><strong>Status:</strong> " . e(status_label($order['status'])) . "</p>";
    if ($note !== '' && $note !== 'Status updated') {
        $html .= "<p><strong>Note from the store:</strong> " . e($note) . "</p>";
    }
    if ($order['status'] === 'completed' && $order['payment_status'] === 'paid') {
        $html .= "<p>Payment of " . peso($order['grand_total']) . " received.</p>";
    }
    $html .= "<h3 style=\"font-family:'Trebuchet MS',Arial,sans-serif;font-size:16px;margin:18px 0 8px;\">Your order</h3>"
        . email_order_table($conn, $order_id)
        . "<p>The receipt for this transaction is attached as a PDF.</p>"
        . email_button(APP_URL . "/cart/order_confirmation.php?id={$id}", 'View your order');

    return send_mail($order['email'], $subject, $html, order_receipt_attachment($conn, $order_id));
}

function send_password_reset_email($email, $name, $link)
{
    $minutes = (int)RESET_LINK_MINUTES;
    $html = "<p>Hi " . e($name) . ",</p>"
        . "<p>We received a request to reset the password for your account. Click the button below to choose a new password.</p>"
        . email_button($link, 'Choose a new password')
        . "<p>This link works once and expires in {$minutes} minutes.</p>"
        . "<p>If you did not ask for this, you can ignore this email. Your password will not change.</p>";
    return send_mail($email, 'Reset your password', $html);
}

function notify_password_changed($email, $name)
{
    $html = "<p>Hi " . e($name) . ",</p>"
        . "<p>The password for your account was just changed.</p>"
        . "<p>If this was you, there is nothing else to do. If it was not, reset your password straight away using the link below.</p>"
        . email_button(APP_URL . '/user/forgot_password.php', 'Reset my password');
    return send_mail($email, 'Your password was changed', $html);
}
