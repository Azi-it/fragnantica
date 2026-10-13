<?php
session_start();
include('../includes/config.php');
include('../includes/functions.php');

// change the quantity of one item in the customer's own cart
require_customer();

if (!isset($_POST['submit'])) {
    header("Location: index.php");
    exit();
}

if (!csrf_valid()) {
    $_SESSION['message'] = 'Your session expired. Please try again.';
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
$qty = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);

if (!$product_id || $qty === false || $qty < 0) {
    $_SESSION['message'] = 'please enter a quantity of 0 or more';
    header("Location: index.php");
    exit();
}

try {
    // the item must already be in this user's cart (user_id from the SESSION)
    $sql = "SELECT c.quantity, p.name, s.quantity AS stock
            FROM cart c
            INNER JOIN product p USING (product_id)
            INNER JOIN stock s USING (product_id)
            WHERE c.user_id = ? AND c.product_id = ? LIMIT 1";
    $row = mysqli_fetch_assoc(mysqli_execute_query($conn, $sql, [$user_id, $product_id]));

    if (!$row) {
        $_SESSION['message'] = 'that item is not in your cart';
    } else if ($qty === 0) {
        mysqli_execute_query($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?", [$user_id, $product_id]);
        $_SESSION['success'] = e($row['name']) . ' removed from your cart';
    } else if ($qty > (int)$row['stock']) {
        $_SESSION['message'] = 'only ' . (int)$row['stock'] . ' of ' . e($row['name']) . ' left in stock';
    } else {
        mysqli_execute_query($conn, "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?", [$qty, $user_id, $product_id]);
        $_SESSION['success'] = 'quantity of ' . e($row['name']) . ' updated to ' . $qty;
    }
} catch (mysqli_sql_exception $e) {
    $_SESSION['message'] = 'could not update your cart, please try again';
}

header("Location: index.php");
exit();
