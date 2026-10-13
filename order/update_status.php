<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can change order status
require_admin();

// only accept the form (POST), never a link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
$note = isset($_POST['note']) && is_string($_POST['note']) ? strip_tags($_POST['note']) : '';

if (!$order_id) {
    header("Location: index.php");
    exit();
}

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: view.php?id={$order_id}");
    exit();
}

// change_order_status checks the move is allowed, restores stock on cancel,
// marks cash on delivery orders paid on completion, and records the history
list($ok, $text) = change_order_status($conn, $order_id, $status, $_SESSION['user_id'], 'admin', $note);

if ($ok) {
    // tell the customer (a failed email does not undo the change)
    if (notify_order_status($conn, $order_id)) {
        $_SESSION['success'] = $text . ' The customer has been emailed the update with the PDF receipt.';
    } else {
        $_SESSION['success'] = $text;
        $_SESSION['message'] = 'The status was saved, but the email to the customer could not be sent. Check the Mailtrap settings in includes/mail_config.php (details are in the PHP error log).';
    }
} else {
    $_SESSION['message'] = $text;
}

header("Location: view.php?id={$order_id}");
exit();
