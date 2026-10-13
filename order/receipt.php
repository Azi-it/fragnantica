<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// the PDF receipt of an order: admins can open any order, a customer only their own
require_login();

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = $order_id ? mysqli_fetch_assoc(mysqli_execute_query($conn,
    "SELECT order_id, user_id FROM order_summary_view WHERE order_id = ? LIMIT 1", [$order_id])) : null;

if (!$order || (!is_admin() && (int)$order['user_id'] !== (int)$_SESSION['user_id'])) {
    $_SESSION['message'] = 'Order not found.';
    header('Location: ' . (is_admin() ? 'index.php' : '../cart/orders.php'));
    exit();
}

$pdf = order_receipt_pdf($conn, $order_id);

// ?download=1 saves the file; otherwise the browser shows it
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Type: application/pdf');
header("Content-Disposition: {$disposition}; filename=\"receipt-order-{$order_id}.pdf\"");
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
exit();
