<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// 1. must be logged in, and 2. admins manage the store, only customers shop
require_customer();

// 3. only accept the form, not someone typing the URL
if (!isset($_POST['submit'])) {
    header("Location: ../index.php");
    exit();
}

// user_id comes from the SESSION, never from the form
$user_id = $_SESSION['user_id'];
$product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
$qty = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);

// where to go afterwards: back to the product page, or back to the store with the same filters.
// only our own pages and known filter names are allowed, so this cannot redirect anywhere else.
$back = '../index.php';
if (($_POST['return'] ?? '') === 'product' && $product_id) {
    $back = '../product/view.php?id=' . (int)$product_id;
} else if (isset($_POST['return_query']) && is_string($_POST['return_query'])) {
    parse_str($_POST['return_query'], $query);
    $allowed = array('search', 'brand', 'scent', 'min_price', 'max_price', 'in_stock', 'sort', 'page');
    $query = array_filter(array_intersect_key($query, array_flip($allowed)), 'is_string');
    if ($query) {
        $back .= '?' . http_build_query($query);
    }
}

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: {$back}");
    exit();
}

if (!$product_id || $qty === false || $qty < 1) {
    $_SESSION['message'] = 'invalid product or quantity';
    header("Location: {$back}");
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
        header("Location: {$back}");
        exit();
    }

    // 6. insert a new row, or update the existing one (upsert)
    $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = ?";
    mysqli_execute_query($conn, $sql, [$user_id, $product_id, $newQty, $newQty]);

    $safeName = e($product['name']);
    $_SESSION['success'] = "added {$qty} x {$safeName} to your cart. <a href='/Fragnantica/cart/index.php' class='alert-link'>View cart</a>";
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'could not add to cart, please try again';
}

header("Location: {$back}");
exit();
