<?php

use PHPMailer\PHPMailer\PHPMailer;

session_start();
include('../includes/config.php');
include('../includes/adminCheck.php');
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../fpdf/fpdf.php';

$order_id = (int)$_POST['order_id'];
$status = trim($_POST['status']);
$back = "orderDetails.php?id={$order_id}";

$statuses = array('pending', 'processing', 'shipped', 'completed', 'cancelled');
if (!in_array($status, $statuses)) {
    $_SESSION['message'] = 'please choose a status';
    header("Location: {$back}");
    exit();
}

$sql = "SELECT status FROM orders WHERE order_id = ? LIMIT 1";
$order = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$order_id]));
if (!$order) {
    $_SESSION['message'] = 'order not found';
    header("Location: orders.php");
    exit();
}
if ($order['status'] === 'cancelled') {
    $_SESSION['message'] = 'this order was cancelled, so its status can no longer be changed';
    header("Location: {$back}");
    exit();
}

// update the status (and return the items to stock when the order is cancelled)
try {
    mysqli_begin_transaction($conn);

    $q = 'UPDATE orders SET status = ? WHERE order_id = ?';
    $stmt1 = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt1, 'si', $status, $order_id);
    mysqli_stmt_execute($stmt1);

    if ($status === 'cancelled') {
        $q2 = 'UPDATE stock s INNER JOIN order_items oi ON oi.product_id = s.product_id SET s.quantity = s.quantity + oi.quantity WHERE oi.order_id = ?';
        $stmt2 = mysqli_prepare($conn, $q2);
        mysqli_stmt_bind_param($stmt2, 'i', $order_id);
        mysqli_stmt_execute($stmt2);
    }

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = 'could not update the order';
    header("Location: {$back}");
    exit();
}

// the customer and the products of the order (from the order_details_view view)
$sql = "SELECT name, email, addressline, town, zipcode, phone, date_placed, product_name, brand, size, quantity, price, subtotal FROM order_details_view WHERE order_id = ?";
$result = mysqli_execute_query($conn, $sql, [$order_id]);
$items = mysqli_fetch_all($result, MYSQLI_ASSOC);
$customer = $items[0];

// ---------- the PDF receipt ----------
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->Cell(0, 10, 'Veloura', 0, 1);
$pdf->SetFont('Helvetica', '', 11);
$pdf->Cell(0, 7, 'Official Receipt', 0, 1);
$pdf->Ln(4);
$pdf->Cell(0, 6, "Order #: {$order_id}", 0, 1);
$pdf->Cell(0, 6, "Date placed: {$customer['date_placed']}", 0, 1);
$pdf->Cell(0, 6, "Status: {$status}", 0, 1);
$pdf->Cell(0, 6, "Customer: {$customer['name']} ({$customer['email']})", 0, 1);
$pdf->Cell(0, 6, "Deliver to: {$customer['addressline']}, {$customer['town']} {$customer['zipcode']}", 0, 1);
$pdf->Cell(0, 6, "Phone: {$customer['phone']}", 0, 1);
$pdf->Ln(4);

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(85, 8, 'Product', 1);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C');
$pdf->Cell(40, 8, 'Price', 1, 0, 'R');
$pdf->Cell(45, 8, 'Subtotal', 1, 1, 'R');
$pdf->SetFont('Helvetica', '', 11);

$grandTotal = 0;
$rows = '';
foreach ($items as $item) {
    $grandTotal += $item['subtotal'];
    $pdf->Cell(85, 8, "{$item['product_name']} ({$item['size']})", 1);
    $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(40, 8, 'PHP ' . number_format($item['price'], 2), 1, 0, 'R');
    $pdf->Cell(45, 8, 'PHP ' . number_format($item['subtotal'], 2), 1, 1, 'R');

    // the same rows for the email
    $rows .= "<tr>
        <td>{$item['product_name']} ({$item['size']})</td>
        <td align='center'>{$item['quantity']}</td>
        <td align='right'>&#8369; " . number_format($item['price'], 2) . "</td>
        <td align='right'>&#8369; " . number_format($item['subtotal'], 2) . "</td>
    </tr>";
}

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(145, 8, 'Grand Total', 1, 0, 'R');
$pdf->Cell(45, 8, 'PHP ' . number_format($grandTotal, 2), 1, 1, 'R');
$pdf->Ln(6);
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(0, 6, 'Thank you for shopping at Veloura!', 0, 1);
// 'S' = return the PDF as a string instead of showing it, so it can be attached to the email
$pdfFile = $pdf->Output('S');

// ---------- the email (sent through Mailtrap) ----------
if (!file_exists('../includes/mail_config.php')) {
    $_SESSION['success'] = "order #{$order_id} is now {$status}";
    $_SESSION['message'] = 'the email was not sent: copy includes/mail_config.example.php to includes/mail_config.php and put your Mailtrap login in it';
    header("Location: {$back}");
    exit();
}
include('../includes/mail_config.php');

$mail = new PHPMailer();
// Server settings
$mail->isSMTP();
$mail->Host = SMTP_HOST;
$mail->SMTPAuth = true;
$mail->Port = SMTP_PORT;
$mail->Username = SMTP_USERNAME;
$mail->Password = SMTP_PASSWORD;

// Recipients
$mail->setFrom(MAIL_FROM, 'Veloura');
$mail->addAddress($customer['email'], $customer['name']);

// Content
$mail->isHTML(true);
$mail->Subject = "Your order #{$order_id} is now {$status}";
$mail->Body = "<p>Hi {$customer['name']},</p>
    <p>The status of your order <strong>#{$order_id}</strong> is now <strong>{$status}</strong>.</p>
    <table border='1' cellpadding='6' cellspacing='0'>
        <tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr>
        {$rows}
        <tr><td colspan='3' align='right'><strong>Grand Total</strong></td><td align='right'><strong>&#8369; " . number_format($grandTotal, 2) . "</strong></td></tr>
    </table>
    <p>Your receipt is attached as a PDF.</p>
    <p>Thank you for shopping at Veloura!</p>";
$mail->AltBody = "The status of your order #{$order_id} is now {$status}. Grand total: PHP " . number_format($grandTotal, 2);
$mail->addStringAttachment($pdfFile, "receipt-order-{$order_id}.pdf");

if ($mail->send()) {
    $_SESSION['success'] = "order #{$order_id} is now {$status}. an email with the PDF receipt was sent to {$customer['email']}";
} else {
    $_SESSION['success'] = "order #{$order_id} is now {$status}";
    $_SESSION['message'] = "the email could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
header("Location: {$back}");
