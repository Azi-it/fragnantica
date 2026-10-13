<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// only admins can delete products
require_admin();

// only accept the form (POST) with its CSRF token, never a plain link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}
if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: index.php");
    exit();
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$product_id) {
    header("Location: index.php");
    exit();
}

// get the photo paths so the files can be removed too
$sql = "SELECT name, img_path FROM product WHERE product_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$product_id]);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    $_SESSION['message'] = 'product not found';
    header("Location: index.php");
    exit();
}
$extraPhotos = mysqli_fetch_all(mysqli_execute_query($conn, "SELECT img_path FROM product_images WHERE product_id = ?", [$product_id]), MYSQLI_ASSOC);

// delete the stock first because it points to the product
// (extra photos, reviews and cart rows are deleted with the product: ON DELETE CASCADE)
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

delete_photo_file(__DIR__ . '/' . $product['img_path']);
foreach ($extraPhotos as $photo) {
    delete_photo_file(__DIR__ . '/' . $photo['img_path']);
}

$_SESSION['success'] = e($product['name']) . ' was deleted.';
header("Location: index.php");
exit();
