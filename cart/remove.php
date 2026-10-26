<?php
session_start();
include('../includes/config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'please log in with a customer account';
    header("Location: ../user/login.php");
    exit();
}

if (isset($_POST['submit'])) {
    $product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);

    if ($product_id) {
        // user_id from the SESSION: you can only delete from YOUR cart
        $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
        mysqli_execute_query($conn, $sql, [$_SESSION['user_id'], $product_id]);
        $_SESSION['success'] = 'item removed from your cart';
    }
}

header("Location: index.php");
exit();
