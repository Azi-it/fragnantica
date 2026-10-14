<?php
session_start();
include('../includes/config.php');

// only admins can delete products
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['message'] = 'admin access only, please log in with an admin account';
    header("Location: ../user/login.php");
    exit();
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$product_id) {
    header("Location: index.php");
    exit();
}

// get the photo path so the file can be removed too
$sql = "SELECT img_path FROM product WHERE product_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$product_id]);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    $_SESSION['message'] = 'product not found';
    header("Location: index.php");
    exit();
}

// delete the stock first because it points to the product
mysqli_begin_transaction($conn);

try {
    $stockStmt = mysqli_prepare($conn, 'DELETE FROM stock WHERE product_id = ?');
    mysqli_stmt_bind_param($stockStmt, 'i', $product_id);
    mysqli_stmt_execute($stockStmt);

    $productStmt = mysqli_prepare($conn, 'DELETE FROM product WHERE product_id = ?');
    mysqli_stmt_bind_param($productStmt, 'i', $product_id);
    mysqli_stmt_execute($productStmt);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $e) {
    // this happens when the product is already part of a customer's order
    mysqli_rollback($conn);
    $_SESSION['message'] = 'Could not delete the product because it is already part of a customer order.';
    header("Location: index.php");
    exit();
}

if (file_exists($product['img_path'])) {
    unlink($product['img_path']);
}

$_SESSION['success'] = 'Product deleted';
header("Location: index.php");
exit();
