<?php
session_start();
include('../includes/config.php');
include('../includes/loginCheck.php');
include('../includes/header.php');

$sql = "SELECT order_id, status, date_placed, total FROM order_totals_view WHERE user_id = ? ORDER BY order_id DESC";
$orders = mysqli_execute_query($conn, $sql, [$_SESSION['user_id']]);
?>
<div class="container mt-4">
    <?php include('../includes/alert.php'); ?>
    <h2>My Orders</h2>
    <?php
    if (mysqli_num_rows($orders) === 0) {
        echo "<p>You have no orders yet. <a href='../index.php'>Shop now</a></p>";
    }
    while ($order = mysqli_fetch_assoc($orders)) {
        echo "<div class='card mb-3'>";
        echo "<div class='card-header'>Order #{$order['order_id']} &middot; {$order['date_placed']} &middot; <strong>{$order['status']}</strong></div>";
        echo "<div class='card-body'><table class='table table-sm'>";
        echo "<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th><th></th></tr></thead><tbody>";

        $sql = "SELECT product_id, product_name, quantity, price, subtotal FROM order_details_view WHERE order_id = ?";
        $items = mysqli_execute_query($conn, $sql, [$order['order_id']]);
        while ($row = mysqli_fetch_assoc($items)) {
            // a completed order can be reviewed
            $reviewLink = '';
            if ($order['status'] === 'completed') {
                $reviewLink = "<a href='../product/view.php?id={$row['product_id']}'>Write a review</a>";
            }
            echo "<tr>";
            echo "<td>{$row['product_name']}</td>";
            echo "<td>{$row['quantity']}</td>";
            echo "<td>{$row['price']}</td>";
            echo "<td>{$row['subtotal']}</td>";
            echo "<td>{$reviewLink}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "<p class='text-end mb-0'><strong>Grand Total: &#8369; {$order['total']}</strong></p>";
        echo "</div></div>";
    }
    ?>
</div>
<?php
include('../includes/footer.php');
?>
