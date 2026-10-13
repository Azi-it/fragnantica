<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// customers can cancel their own order while it is still pending
require_customer();

// only accept the form (POST), never a link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: orders.php");
    exit();
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: orders.php");
    exit();
}

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: order_confirmation.php?id={$order_id}");
    exit();
}

// change_order_status checks that the order belongs to this customer and is still pending
list($ok, $text) = change_order_status($conn, $order_id, 'cancelled', $_SESSION['user_id'], 'customer');

if ($ok) {
    // confirmation email (a failed email does not undo the cancellation)
    notify_order_status($conn, $order_id);
    $_SESSION['success'] = $text;
} else {
    $_SESSION['message'] = $text;
}

header("Location: order_confirmation.php?id={$order_id}");
exit();
