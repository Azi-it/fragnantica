<?php
session_start();
include('../includes/config.php');

// 1. must be logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'please log in to add items to your cart';
    header("Location: ../user/login.php");
    exit();
}

// 2. admins manage the store, only customers shop
if ($_SESSION['role'] !== 'customer') {
    $_SESSION['message'] = 'only customers can use the cart';
    header("Location: ../index.php");
    exit();
}

// 3. only accept the form, not someone typing the URL
if (!isset($_POST['submit'])) {
    header("Location: ../index.php");
    exit();
}

// user_id comes from the SESSION, never from the form
$user_id = $_SESSION['user_id'];
$product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
$qty = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);

if (!$product_id || $qty === false || $qty < 1) {
    $_SESSION['message'] = 'invalid product or quantity';
    header("Location: ../index.php");
    exit();
}

try {
    // 4. does the product exist, and how many are in stock?
    $sql = "SELECT p.name, s.quantity FROM product p
            INNER JOIN stock s USING (product_id)
            WHERE p.product_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$product_id]);
    $product = mysqli_fetch_assoc($result);

    if (!$product) {
        $_SESSION['message'] = 'product not found';
        header("Location: ../index.php");
        exit();
    }

    // 5. how many does this user already have in the cart?
    $sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, [$user_id, $product_id]);
    $inCart = mysqli_fetch_assoc($result);
    $currentQty = $inCart ? (int)$inCart['quantity'] : 0;

    $newQty = $currentQty + $qty;
    $stock = (int)$product['quantity'];

    if ($newQty > $stock) {
        $_SESSION['message'] = "only {$stock} in stock (you already have {$currentQty} in your cart)";
        header("Location: ../index.php");
        exit();
    }

    // 6. insert a new row, or update the existing one (upsert)
    $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = ?";
    mysqli_execute_query($conn, $sql, [$user_id, $product_id, $newQty, $newQty]);

    $safeName = htmlspecialchars($product['name'], ENT_QUOTES);
    $_SESSION['success'] = "added {$qty} x {$safeName} to your cart";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'could not add to cart, please try again';
}

header("Location: ../index.php");
exit();
