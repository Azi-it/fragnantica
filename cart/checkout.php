<?php
session_start();
include('../includes/config.php');
include('../includes/loginCheck.php');

if (!isset($_SESSION["cart_products"]) || count($_SESSION["cart_products"]) === 0) {
    $_SESSION['message'] = 'your cart is empty';
    header('Location: view_cart.php');
    exit();
}

// the delivery details must be filled in first
$sql = "SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, [$_SESSION['user_id']]);
if (mysqli_num_rows($result) === 0) {
    $_SESSION['message'] = 'please fill in your delivery details before checking out';
    header('Location: ../user/profile.php');
    exit();
}

// the whole order is saved in one transaction with prepared statements:
// if one step fails, nothing is saved
try {
    mysqli_begin_transaction($conn);

    $q = 'INSERT INTO orders (user_id, status, date_placed) VALUES (?, \'pending\', NOW())';
    $stmt1 = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt1, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt1);
    $order_id = mysqli_insert_id($conn);

    $q2 = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
    $stmt2 = mysqli_prepare($conn, $q2);
    mysqli_stmt_bind_param($stmt2, 'iiid', $order_id, $product_code, $product_qty, $product_price);

    $q3 = 'UPDATE stock SET quantity = quantity - ? WHERE product_id = ?';
    $stmt3 = mysqli_prepare($conn, $q3);
    mysqli_stmt_bind_param($stmt3, 'ii', $product_qty, $product_code);

    $q4 = 'SELECT p.name, p.sell_price, s.quantity FROM product p INNER JOIN stock s USING (product_id) WHERE p.product_id = ? FOR UPDATE';

    foreach ($_SESSION["cart_products"] as $cart_itm) {
        $product_qty = $cart_itm["product_qty"];
        $product_code = $cart_itm["product_id"];

        // check the stock first, and use today's price
        $row = mysqli_fetch_assoc(mysqli_execute_query($conn, $q4, [$product_code]));
        if (!$row || $row['quantity'] < $product_qty) {
            mysqli_rollback($conn);
            $_SESSION['message'] = "not enough stock for {$cart_itm['product_name']}, please change the quantity";
            header('Location: view_cart.php');
            exit();
        }
        $product_price = $row['sell_price'];

        mysqli_stmt_execute($stmt2);
        mysqli_stmt_execute($stmt3);
    }

    mysqli_commit($conn);
    unset($_SESSION['cart_products']);
    $_SESSION['success'] = "order #{$order_id} placed, thank you!";
    header('Location: ../user/myorders.php');
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = 'checkout failed, please try again';
    header('Location: view_cart.php');
}
